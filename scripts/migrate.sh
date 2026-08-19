#!/usr/bin/env bash
# ==============================================================================
# migrate.sh — export DB + uploads jadi 1 archive (portable antar server)
#
# Usage:
#   ./scripts/migrate.sh export [output.tar.gz]
#   ./scripts/migrate.sh import <archive.tar.gz> [--from-url=https://old] [--to-url=https://new]
#
# Design:
# - Archive berisi: dump.sql.gz + uploads.tar.gz + meta.json
# - Import otomatis jalankan `wp search-replace` kalau --from-url & --to-url ada
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd docker

COMPOSE="docker compose"

usage() {
    cat <<EOF
Usage:
  $0 export [OUTPUT_FILE]
      Export DB + uploads dari container ke <OUTPUT_FILE> (default: migrate-<date>.tar.gz)

  $0 import ARCHIVE [--from-url=URL] [--to-url=URL]
      Import archive ke stack yang sedang jalan. Kalau --from-url & --to-url disebut,
      otomatis jalankan 'wp search-replace' setelah import.

  $0 --help
EOF
}

cmd_export() {
    local out="${1:-migrate-$(date +%Y%m%d-%H%M%S).tar.gz}"
    local work
    work="$(mktemp -d)"
    trap 'rm -rf "$work"' RETURN

    log_info "Exporting DB ..."
    ${COMPOSE} exec -T backup /usr/local/bin/backup.sh
    ${COMPOSE} exec -T backup sh -c 'ls -t /backups/db/daily/*.sql.gz | head -n1 | xargs cat' > "${work}/dump.sql.gz"

    log_info "Exporting uploads ..."
    ${COMPOSE} exec -T backup sh -c 'tar czf - -C / uploads' > "${work}/uploads.tar.gz"

    log_info "Writing meta.json ..."
    local wp_home
    wp_home="$(${COMPOSE} exec -T -u www-data app wp option get home --path=/app/web/wp 2>/dev/null | tr -d '\r' || echo '')"
    cat > "${work}/meta.json" <<EOF
{
  "exported_at": "$(date -Iseconds)",
  "wp_home": "${wp_home}",
  "boilerplate_version": "1.0.0"
}
EOF

    tar czf "${out}" -C "${work}" .
    log_ok  "Exported to ${out} ($(du -h "${out}" | cut -f1))"
}

cmd_import() {
    local archive="${1:?Usage: $0 import <archive.tar.gz>}"
    shift
    local from_url="" to_url=""
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --from-url=*) from_url="${1#*=}" ;;
            --to-url=*)   to_url="${1#*=}"   ;;
            *) die "Unknown arg: $1" ;;
        esac
        shift
    done

    [[ -f "${archive}" ]] || die "Archive not found: ${archive}"

    local work
    work="$(mktemp -d)"
    trap 'rm -rf "$work"' RETURN

    log_info "Extracting ${archive} ..."
    tar xzf "${archive}" -C "${work}"

    if [[ -f "${work}/meta.json" ]]; then
        log_info "Archive metadata:"
        cat "${work}/meta.json"
    fi

    log_warn "About to REPLACE current DB + uploads. This is destructive."
    confirm "Continue?" || die "Cancelled"

    log_info "Restoring DB ..."
    ${COMPOSE} exec -T backup sh -c "cat > /backups/db/import.sql.gz" < "${work}/dump.sql.gz"
    "${SCRIPT_DIR}/restore-backup.sh" --yes /backups/db/import.sql.gz

    log_info "Restoring uploads ..."
    ${COMPOSE} exec -T backup sh -c 'rm -rf /uploads/* && tar xzf - -C /' < "${work}/uploads.tar.gz"

    if [[ -n "${from_url}" && -n "${to_url}" ]]; then
        log_info "Running wp search-replace '${from_url}' → '${to_url}' ..."
        ${COMPOSE} exec -T -u www-data app wp search-replace \
            "${from_url}" "${to_url}" \
            --all-tables \
            --skip-columns=guid \
            --path=/app/web/wp
        ${COMPOSE} exec -T -u www-data app wp cache flush --path=/app/web/wp
    else
        log_warn "URL replacement skipped. Jalankan manual kalau perlu:"
        log_warn "  ${COMPOSE} exec -u www-data app wp search-replace <old> <new> --all-tables --path=/app/web/wp"
    fi

    log_ok "Import complete"
}

case "${1:-}" in
    export)   shift; cmd_export "$@" ;;
    import)   shift; cmd_import "$@" ;;
    --help|-h|"") usage ;;
    *) die "Unknown command: $1 (use --help)" ;;
esac
