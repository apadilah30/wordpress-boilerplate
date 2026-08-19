#!/usr/bin/env bash
# ==============================================================================
# backup.sh — daily DB dump + weekly uploads snapshot + rotation
#
# ENV yang dibaca (di-set di docker-compose.yml service 'backup'):
#   DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
#   BACKUP_RETENTION_DAILY   (default 30)
#   BACKUP_RETENTION_WEEKLY  (default 8)
#   BACKUP_RETENTION_MONTHLY (default 12)
# ==============================================================================

set -euo pipefail

: "${DB_HOST:?}"
: "${DB_NAME:?}"
: "${DB_USER:?}"
: "${DB_PASSWORD:?}"

RETENTION_DAILY="${BACKUP_RETENTION_DAILY:-30}"
RETENTION_WEEKLY="${BACKUP_RETENTION_WEEKLY:-8}"
RETENTION_MONTHLY="${BACKUP_RETENTION_MONTHLY:-12}"

BACKUP_ROOT="/backups"
DB_DIR="${BACKUP_ROOT}/db"
FILES_DIR="${BACKUP_ROOT}/files"
LAST_SUCCESS_FILE="${BACKUP_ROOT}/last_success"

mkdir -p "${DB_DIR}/daily" "${DB_DIR}/weekly" "${DB_DIR}/monthly" "${FILES_DIR}"

log() { printf '[backup] %s %s\n' "$(date -Iseconds)" "$*"; }

# ------------------------------------------------------------------
# DB dump — harian
# ------------------------------------------------------------------
ts="$(date +%Y-%m-%d_%H%M%S)"
dump_file="${DB_DIR}/daily/db-${ts}.sql.gz"

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
# Weekly rotation — copy dump ke weekly/ tiap Minggu
# ------------------------------------------------------------------
if [[ "$(date +%u)" == "7" ]]; then
    cp "${dump_file}" "${DB_DIR}/weekly/db-week-$(date +%Y-W%V).sql.gz"
    log "Weekly snapshot copied"
fi

# ------------------------------------------------------------------
# Monthly rotation — copy dump ke monthly/ tiap tanggal 1
# ------------------------------------------------------------------
if [[ "$(date +%d)" == "01" ]]; then
    cp "${dump_file}" "${DB_DIR}/monthly/db-$(date +%Y-%m).sql.gz"
    log "Monthly snapshot copied"
fi

# ------------------------------------------------------------------
# Uploads snapshot — mingguan (KISS: tar+gzip, bukan restic)
# ------------------------------------------------------------------
if [[ "$(date +%u)" == "7" ]] && [[ -d /uploads ]]; then
    uploads_file="${FILES_DIR}/uploads-$(date +%Y-W%V).tar.gz"
    log "Snapshotting uploads → ${uploads_file}"
    tar czf "${uploads_file}.tmp" -C / uploads
    mv "${uploads_file}.tmp" "${uploads_file}"
    log "OK: uploads snapshot size $(du -h "${uploads_file}" | cut -f1)"
fi

# ------------------------------------------------------------------
# Retention: hapus file lama
# ------------------------------------------------------------------
find "${DB_DIR}/daily"   -type f -name "db-*.sql.gz" -mtime "+${RETENTION_DAILY}"   -delete
find "${DB_DIR}/weekly"  -type f -name "db-*.sql.gz" -mtime "+$(( RETENTION_WEEKLY * 7 ))"  -delete
find "${DB_DIR}/monthly" -type f -name "db-*.sql.gz" -mtime "+$(( RETENTION_MONTHLY * 31 ))" -delete
# Uploads snapshot retention: sama dengan weekly DB
find "${FILES_DIR}"      -type f -name "uploads-*.tar.gz" -mtime "+$(( RETENTION_WEEKLY * 7 ))" -delete

# ------------------------------------------------------------------
# Health marker — dipakai monitoring untuk cek backup masih jalan
# ------------------------------------------------------------------
date -Iseconds > "${LAST_SUCCESS_FILE}"
log "Done. Health marker updated."
