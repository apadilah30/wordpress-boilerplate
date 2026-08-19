#!/usr/bin/env bash
# ==============================================================================
# FrankenPHP entrypoint
#
# Tugas:
# 1. Kalau APP_ENV=development → install composer deps & pastikan uploads writable
# 2. Kalau FRANKENPHP_WORKER_MODE=1 → aktifkan worker mode di Caddyfile
# 3. Tunggu DB siap (max 30 detik) — biar `wp` command tidak error saat first boot
# 4. Exec original command
# ==============================================================================

set -euo pipefail

APP_ENV="${APP_ENV:-production}"
FRANKENPHP_WORKER_MODE="${FRANKENPHP_WORKER_MODE:-0}"
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"

log() { printf '[entrypoint] %s\n' "$*" >&2; }

# ------------------------------------------------------------------
# Dev: composer install pada startup (source di-mount, vendor kosong).
# Prod: skip — sudah di-copy dari stage 'vendor'.
# ------------------------------------------------------------------
if [[ "${APP_ENV}" == "development" ]]; then
    if [[ ! -d /app/vendor ]] || [[ ! -f /app/vendor/autoload.php ]]; then
        log "Development env — running composer install"
        cd /app
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi
fi

# ------------------------------------------------------------------
# Uploads harus writable ownership www-data (id 33).
# ------------------------------------------------------------------
mkdir -p /app/web/app/uploads /app/web/app/cache /app/web/app/upgrade
if [[ "$(id -u)" == "0" ]]; then
    chown -R www-data:www-data /app/web/app/uploads /app/web/app/cache /app/web/app/upgrade
fi

# ------------------------------------------------------------------
# Worker mode toggle
# ------------------------------------------------------------------
if [[ "${FRANKENPHP_WORKER_MODE}" == "1" ]]; then
    log "Enabling FrankenPHP worker mode"
    sed -i 's|# worker /app/web/index.php|worker /app/web/index.php|' /etc/caddy/Caddyfile
fi

# ------------------------------------------------------------------
# Wait for DB (best-effort, 30s max)
# ------------------------------------------------------------------
log "Waiting for database ${DB_HOST}:${DB_PORT} ..."
for i in {1..30}; do
    if (echo >/dev/tcp/"${DB_HOST}"/"${DB_PORT}") 2>/dev/null; then
        log "Database is up"
        break
    fi
    sleep 1
done

exec "$@"
