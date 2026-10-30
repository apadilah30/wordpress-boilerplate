#!/usr/bin/env bash
# ==============================================================================
# migrate-category.sh — pindahkan kategori dari taxonomy bawaan "category" ke
# taxonomy khusus sebuah post type (konvensi {post_type}_category).
#
# Yang dilakukan (logikanya ada di scripts/lib/migrate-category.php):
#   1. Membuat term yang sama di taxonomy baru (hierarki parent/child ikut).
#   2. Memasang post milik post type itu ke term baru.
#   3. Melepas relasi lamanya ke "category".
# Term lama di "category" tidak dihapus.
#
# Default-nya DRY RUN (hanya menampilkan rencana). Tambahkan --apply untuk
# benar-benar mengubah data. Backup database dulu sebelum --apply.
#
# Usage:
#   ./scripts/migrate-category.sh <post_type> <new_taxonomy> [--apply]
#
# Contoh:
#   ./scripts/migrate-category.sh services services_category           # cek dulu
#   ./scripts/migrate-category.sh services services_category --apply  # jalankan
#   ./scripts/migrate-category.sh news news_category --apply
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd docker

POST_TYPE="${1:-}"
NEW_TAXONOMY="${2:-}"
MODE="${3:-}"

[[ -n "${POST_TYPE}" && -n "${NEW_TAXONOMY}" ]] || \
    die "Usage: ./scripts/migrate-category.sh <post_type> <new_taxonomy> [--apply]"

WP_ARGS=("${POST_TYPE}" "${NEW_TAXONOMY}")
case "${MODE}" in
    "")
        log_info "Mode DRY RUN: tidak ada data yang diubah."
        ;;
    --apply)
        WP_ARGS+=("apply")
        log_info "Mode APPLY: data akan diubah. Pastikan database sudah di-backup."
        ;;
    *)
        die "Argumen ketiga hanya boleh --apply (atau kosongkan untuk dry run)."
        ;;
esac

REPO_ROOT="$(repo_root)"
cd "${REPO_ROOT}"

CONTAINER_FILE="/tmp/wptg-migrate-category.php"

# Folder scripts/ tidak di-mount ke container, jadi file PHP disalin dulu.
docker compose cp "scripts/lib/migrate-category.php" "app:${CONTAINER_FILE}"
trap 'docker compose exec -T app rm -f "${CONTAINER_FILE}" >/dev/null 2>&1 || true' EXIT

docker compose exec -T -u www-data app wp eval-file "${CONTAINER_FILE}" "${WP_ARGS[@]}" --path=/app/web/wp
