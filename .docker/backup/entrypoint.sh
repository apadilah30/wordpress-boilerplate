#!/usr/bin/env bash
# ==============================================================================
# Backup entrypoint — install cron entry dari env BACKUP_CRON dan jalankan
# supercronic (cron modern yang PID 1-friendly, log ke stdout).
# ==============================================================================

set -euo pipefail

BACKUP_CRON="${BACKUP_CRON:-0 3 * * *}"
CRONTAB_FILE="/etc/supercronic/crontab"

mkdir -p /etc/supercronic
{
    echo "# Auto-generated dari env BACKUP_CRON"
    echo "${BACKUP_CRON} /usr/local/bin/backup.sh"
} > "${CRONTAB_FILE}"

echo "[backup] cron schedule: ${BACKUP_CRON}"
echo "[backup] backups dir: /backups (retention=${BACKUP_RETENTION_DAYS:-30} hari)"

# Backup pertama kali langsung jalan supaya kita tahu koneksi DB OK
if /usr/local/bin/backup.sh; then
    echo "[backup] initial backup OK"
else
    echo "[backup] WARNING: initial backup failed — cron tetap jalan, cek DB/credentials"
fi

exec supercronic "${CRONTAB_FILE}"
