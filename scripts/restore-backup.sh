#!/usr/bin/env bash
# ==============================================================================
# restore-backup.sh — restore DB dari file backup
#
# Usage:
#   ./scripts/restore-backup.sh <path-ke-dump.sql.gz>
#   ./scripts/restore-backup.sh --latest         # ambil dump terbaru
#   ./scripts/restore-backup.sh --list            # list semua backup
#
# Otomatis:
# - Pre-restore snapshot DB current
# - Konfirmasi user (kecuali --yes)
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd docker

COMPOSE="docker compose"
BACKUP_CONTAINER="wp-boilerplate-backup-1"
YES=0
FILE=""

usage() {
    cat <<EOF
Usage:
  $0 <path-ke-dump.sql.gz>   Restore dari file spesifik (path di dalam container /backups/)
  $0 --latest                 Restore backup harian terbaru
  $0 --list                   Tampilkan semua backup tersedia
  $0 --help                   Show this help

Options:
  --yes                       Skip konfirmasi
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --list)   ${COMPOSE} exec -T backup ls -lh /backups/db/daily /backups/db/weekly /backups/db/monthly; exit 0 ;;
        --latest) FILE="$(${COMPOSE} exec -T backup sh -c 'ls -t /backups/db/daily/*.sql.gz | head -n1' | tr -d '\r')" ;;
        --yes)    YES=1 ;;
        --help|-h) usage; exit 0 ;;
        -*)       die "Unknown option: $1" ;;
        *)        FILE="$1" ;;
    esac
    shift
done

[[ -n "${FILE}" ]] || { usage; die "Please specify a backup file (or --latest)"; }

log_warn "About to RESTORE database from: ${FILE}"
log_warn "This will OVERWRITE the current database."

if [[ ${YES} -eq 0 ]]; then
    confirm "Continue?" || die "Cancelled by user"
fi

# Load DB creds dari .env kalau ada
if [[ -f .env ]]; then
    # shellcheck disable=SC1091
    set -a; source .env; set +a
fi

require_env DB_NAME DB_USER DB_PASSWORD

# Pre-restore snapshot (safety net)
log_info "Taking pre-restore snapshot..."
${COMPOSE} exec -T backup /usr/local/bin/backup.sh || log_warn "Pre-restore snapshot gagal — lanjut restore"

log_info "Restoring ${FILE} ..."
${COMPOSE} exec -T backup sh -c "gunzip -c '${FILE}' | MYSQL_PWD='${DB_PASSWORD}' mysql -h db -u '${DB_USER}' '${DB_NAME}'"
log_ok "Restore complete"

log_info "Running wp cache flush + rewrite flush..."
${COMPOSE} exec -T -u www-data app wp cache flush --path=/app/web/wp   || log_warn "cache flush gagal"
${COMPOSE} exec -T -u www-data app wp rewrite flush --path=/app/web/wp || log_warn "rewrite flush gagal"

log_ok "Done."
