#!/usr/bin/env bash
# ==============================================================================
# add-custom-plugin.sh — helper untuk add plugin custom (non-WPackagist)
#
# Otomatis:
# 1. Extract zip / copy folder ke plugins-custom/<slug>/
# 2. Generate composer.json minimal kalau belum ada
# 3. Register di composer.json root via `composer require`
# 4. Print instruksi commit (TIDAK auto-commit — sesuai AGENTS.md)
#
# Usage:
#   ./scripts/add-custom-plugin.sh <path-to-zip-or-folder> [--vendor=myorg] [--version=1.0.0]
#
# Contoh:
#   ./scripts/add-custom-plugin.sh ~/Downloads/elementor-pro.zip --vendor=premium --version=3.23.0
#   ./scripts/add-custom-plugin.sh ~/my-internal-plugin        --vendor=myorg
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd composer unzip

SRC=""
VENDOR="myorg"
VERSION="1.0.0"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --vendor=*)  VENDOR="${1#*=}" ;;
        --version=*) VERSION="${1#*=}" ;;
        --help|-h)
            grep '^#' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        -*) die "Unknown option: $1" ;;
        *)  SRC="$1" ;;
    esac
    shift
done

[[ -n "${SRC}" ]] || die "Source path required (use --help for usage)"
[[ -e "${SRC}" ]] || die "Source not found: ${SRC}"

REPO_ROOT="$(repo_root)"
cd "${REPO_ROOT}"

# ------------------------------------------------------------------
# 1. Detect slug & extract/copy
# ------------------------------------------------------------------
SLUG=""
if [[ -d "${SRC}" ]]; then
    SLUG="$(basename "${SRC}")"
    log_info "Copying folder ${SRC} → plugins-custom/${SLUG}/"
    mkdir -p "plugins-custom/${SLUG}"
    cp -R "${SRC}/." "plugins-custom/${SLUG}/"
elif [[ "${SRC}" == *.zip ]]; then
    tmp="$(mktemp -d)"
    log_info "Extracting ${SRC} → ${tmp}"
    unzip -q "${SRC}" -d "${tmp}"
    # Cari root plugin folder (biasanya 1 folder di dalam zip)
    root_dirs=("${tmp}"/*/)
    if [[ ${#root_dirs[@]} -eq 1 ]]; then
        SLUG="$(basename "${root_dirs[0]}")"
        mkdir -p "plugins-custom/${SLUG}"
        cp -R "${root_dirs[0]}." "plugins-custom/${SLUG}/"
    else
        SLUG="$(basename "${SRC}" .zip)"
        mkdir -p "plugins-custom/${SLUG}"
        cp -R "${tmp}/." "plugins-custom/${SLUG}/"
    fi
    rm -rf "${tmp}"
else
    die "Source must be a folder or .zip file"
fi

log_ok "Plugin folder ready: plugins-custom/${SLUG}/"

# ------------------------------------------------------------------
# 2. Generate composer.json kalau belum ada
# ------------------------------------------------------------------
COMPOSER_FILE="plugins-custom/${SLUG}/composer.json"
if [[ ! -f "${COMPOSER_FILE}" ]]; then
    log_info "Generating minimal composer.json ..."
    cat > "${COMPOSER_FILE}" <<EOF
{
    "name": "${VENDOR}/${SLUG}",
    "type": "wordpress-plugin",
    "version": "${VERSION}",
    "license": "GPL-2.0-or-later",
    "require": {
        "composer/installers": "^2.0"
    }
}
EOF
else
    log_info "composer.json already exists — leaving as-is"
fi

# ------------------------------------------------------------------
# 3. Register di composer.json root
# ------------------------------------------------------------------
PACKAGE_NAME="$(php -r '$j = json_decode(file_get_contents($argv[1]), true); echo $j["name"];' "${COMPOSER_FILE}")"
log_info "Registering ${PACKAGE_NAME} via composer require ..."
composer require "${PACKAGE_NAME}:*" --no-interaction --no-scripts

# ------------------------------------------------------------------
# 4. Instruksi commit — TIDAK auto-commit (per AGENTS.md)
# ------------------------------------------------------------------
cat <<EOF

$(log_ok "Plugin ${SLUG} added.")

Next steps (jalankan manual):
  1. Review:    git status
  2. Stage:     git add plugins-custom/${SLUG} composer.json composer.lock
  3. Commit:    git commit -m "add: ${SLUG} v${VERSION}"
  4. Push:      git push
  5. Aktifkan di container: docker compose exec -u www-data app wp plugin activate ${SLUG} --path=/app/web/wp

EOF
