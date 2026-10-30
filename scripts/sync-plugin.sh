#!/usr/bin/env bash
# ==============================================================================
# sync-plugin.sh — force re-sync satu plugin custom ke container setelah edit
# (atau setelah plugin baru pertama kali ditambahkan).
#
# Kenapa perlu 2 langkah:
#   1. `composer update <package>` — WAJIB untuk plugin yang BARU ditambahkan
#      ke composer.json root (belum ada di composer.lock). Tanpa ini,
#      `composer install` tidak akan menginstallnya sama sekali (install
#      cuma baca apa yang SUDAH ada di lock file).
#   2. hapus folder tujuan + `composer install` — WAJIB untuk memastikan
#      perubahan KODE ter-copy ulang. composer.json root pakai "path"
#      repository dengan "symlink": false (plugin di-COPY, bukan di-link),
#      dan `composer update` kadang tidak mendeteksi perubahan isi file
#      plugin custom (tidak ada versi/hash baru terdeteksi).
#
# Usage:
#   ./scripts/sync-plugin.sh <plugin-slug>
#
# Contoh:
#   ./scripts/sync-plugin.sh wp-taxonomy-grid
#   ./scripts/sync-plugin.sh wp-content-grid
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd docker

SLUG="${1:-}"
[[ -n "${SLUG}" ]] || die "Usage: ./scripts/sync-plugin.sh <plugin-slug> (contoh: wp-taxonomy-grid)"

REPO_ROOT="$(repo_root)"
cd "${REPO_ROOT}"

PLUGIN_DIR="plugins-custom/${SLUG}"
[[ -d "${PLUGIN_DIR}" ]] || die "${PLUGIN_DIR} tidak ditemukan"
[[ -f "${PLUGIN_DIR}/composer.json" ]] || die "${PLUGIN_DIR}/composer.json tidak ditemukan"

# Semua plugin custom di project ini pakai konvensi nama "boilerplate/<slug>"
# (lihat plugins-custom/*/composer.json) — tidak perlu parse JSON, tinggal
# susun langsung. Diverifikasi harus match isi composer.json biar aman
# kalau suatu saat ada plugin dengan vendor beda.
PACKAGE_NAME="boilerplate/${SLUG}"

if ! grep -q "\"name\"[[:space:]]*:[[:space:]]*\"${PACKAGE_NAME}\"" "${PLUGIN_DIR}/composer.json"; then
    die "Package name di ${PLUGIN_DIR}/composer.json tidak match konvensi 'boilerplate/${SLUG}' — cek manual nama package-nya lalu jalankan: docker compose exec app composer update <nama-package> --no-interaction"
fi

log_info "Package: ${PACKAGE_NAME}"

log_info "Step 1/3: composer update ${PACKAGE_NAME} (perlu untuk plugin baru / resolve versi) ..."
docker compose exec app composer update "${PACKAGE_NAME}" --no-interaction

log_info "Step 2/3: hapus web/app/plugins/${SLUG} lalu composer install (force re-copy) ..."
docker compose exec app rm -rf "web/app/plugins/${SLUG}"
docker compose exec app composer install --no-interaction

log_info "Step 3/3: verifikasi ..."
docker compose exec app grep -m1 -i "Version:" "web/app/plugins/${SLUG}/${SLUG}.php" || \
    log_info "(file plugin tidak ditemukan di web/app/plugins/${SLUG}/ — cek error di atas)"

docker compose exec -u www-data app wp plugin list --path=/app/web/wp | grep "${SLUG}" || \
    log_info "(plugin belum aktif — jalankan: docker compose exec -u www-data app wp plugin activate ${SLUG} --path=/app/web/wp)"

cat <<EOF

$(log_ok "Sync selesai.")

Langkah selanjutnya:
  1. Kalau belum aktif, jalankan:
     docker compose exec -u www-data app wp plugin activate ${SLUG} --path=/app/web/wp
  2. Reload halaman edit Elementor (full reload, bukan cuma refresh preview)

EOF
