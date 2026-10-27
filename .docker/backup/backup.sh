#!/usr/bin/env bash
# ==============================================================================
# backup.sh — daily DB dump + daily project snapshot + rotation
#
# ENV yang dibaca (di-set di docker-compose.yml service 'backup'):
#   DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
#   BACKUP_RETENTION_DAYS    (default 30) — berapa hari backup disimpan
#   PROJECT_DIR              (default /app) — root project yang di-backup
# ==============================================================================

set -euo pipefail

: "${DB_HOST:?}"
: "${DB_NAME:?}"
: "${DB_USER:?}"
: "${DB_PASSWORD:?}"

RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
PROJECT_DIR="${PROJECT_DIR:-/app}"

BACKUP_ROOT="/backups"
DB_DIR="${BACKUP_ROOT}/db"
PROJECT_BACKUP_DIR="${BACKUP_ROOT}/project"
LAST_SUCCESS_FILE="${BACKUP_ROOT}/last_success"

mkdir -p "${DB_DIR}" "${PROJECT_BACKUP_DIR}"

log() { printf '[backup] %s %s\n' "$(date -Iseconds)" "$*"; }

ts="$(date +%Y-%m-%d_%H%M%S)"

# ------------------------------------------------------------------
# 1. DB dump — harian
# ------------------------------------------------------------------
dump_file="${DB_DIR}/db-${ts}.sql.gz"

log "Dumping ${DB_NAME} from ${DB_HOST} → ${dump_file}"
if ! MYSQL_PWD="${DB_PASSWORD}" mysqldump \
        --host="${DB_HOST}" \
        --user="${DB_USER}" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" \
    | gzip -9 > "${dump_file}.tmp"; then
    log "ERROR: mysqldump failed"
    rm -f "${dump_file}.tmp"
    exit 1
fi
mv "${dump_file}.tmp" "${dump_file}"
log "OK: DB dump size $(du -h "${dump_file}" | cut -f1)"

# ------------------------------------------------------------------
# 2. Full project snapshot — harian
#    Meng-backup seluruh project (source code, config, themes, plugins-custom,
#    mu-plugins). Folder vendor/, web/wp/, web/app/plugins/ dikecualikan
#    karena bisa di-regenerate via composer install.
#    Uploads di-backup terpisah supaya mudah di-restore parsial.
# ------------------------------------------------------------------
project_file="${PROJECT_BACKUP_DIR}/project-${ts}.tar.gz"

if [[ -d "${PROJECT_DIR}" ]]; then
    log "Snapshotting project ${PROJECT_DIR} → ${project_file}"
    tar czf "${project_file}.tmp" \
        --exclude="${PROJECT_DIR}/vendor" \
        --exclude="${PROJECT_DIR}/web/wp" \
        --exclude="${PROJECT_DIR}/web/app/plugins" \
        --exclude="${PROJECT_DIR}/web/app/uploads" \
        -C "$(dirname "${PROJECT_DIR}")" \
        "$(basename "${PROJECT_DIR}")"
    mv "${project_file}.tmp" "${project_file}"
    log "OK: project snapshot size $(du -h "${project_file}" | cut -f1)"
else
    log "WARNING: PROJECT_DIR=${PROJECT_DIR} tidak ditemukan, skip project snapshot"
fi

# ------------------------------------------------------------------
# 3. Uploads snapshot — harian (terpisah agar bisa restore parsial)
# ------------------------------------------------------------------
uploads_file="${BACKUP_ROOT}/uploads/uploads-${ts}.tar.gz"
mkdir -p "${BACKUP_ROOT}/uploads"

if [[ -d /uploads ]]; then
    log "Snapshotting uploads → ${uploads_file}"
    tar czf "${uploads_file}.tmp" -C / uploads
    mv "${uploads_file}.tmp" "${uploads_file}"
    log "OK: uploads snapshot size $(du -h "${uploads_file}" | cut -f1)"
else
    log "INFO: /uploads tidak ditemukan (volume belum mount?), skip uploads snapshot"
fi

# ------------------------------------------------------------------
# 4. Retention: hapus semua backup lebih dari RETENTION_DAYS hari
# ------------------------------------------------------------------
log "Menjalankan retention: hapus backup lebih dari ${RETENTION_DAYS} hari"

find "${DB_DIR}"                  -type f -name "db-*.sql.gz"        -mtime "+${RETENTION_DAYS}" -delete
find "${PROJECT_BACKUP_DIR}"      -type f -name "project-*.tar.gz"   -mtime "+${RETENTION_DAYS}" -delete
find "${BACKUP_ROOT}/uploads"     -type f -name "uploads-*.tar.gz"   -mtime "+${RETENTION_DAYS}" -delete 2>/dev/null || true

log "Retention selesai."

# ------------------------------------------------------------------
# 5. Health marker — dipakai monitoring untuk cek backup masih jalan
# ------------------------------------------------------------------
date -Iseconds > "${LAST_SUCCESS_FILE}"
log "Done. Health marker updated."
