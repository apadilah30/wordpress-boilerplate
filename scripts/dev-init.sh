#!/usr/bin/env bash
# ==============================================================================
# dev-init.sh — One-command first-time setup untuk dev environment
#
# Yang dikerjain:
# 1. Cek .env ada (kalau belum, copy dari .env.example)
# 2. Build image
# 3. Start containers (dengan --wait supaya block sampai healthy)
# 4. Tunggu composer install selesai (via monitoring log)
# 5. Install WordPress core (wp core install)
# 6. Aktifkan starter theme + Elementor
#
# Idempotent — aman dijalankan berulang. Kalau WP sudah terinstall, skip step 5-6.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd docker

REPO_ROOT="$(repo_root)"
cd "${REPO_ROOT}"

COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.dev.yml)

# ------------------------------------------------------------------
# 1. .env check
# ------------------------------------------------------------------
if [[ ! -f .env ]]; then
    log_warn ".env tidak ada — copy dari .env.example"
    cp .env.example .env
    log_warn "PENTING: edit .env dan set salts baru dari https://roots.io/salts.html"
    log_warn "         set juga DB_PASSWORD dan DB_ROOT_PASSWORD"
    log_warn ""
    log_warn "Setelah edit .env, jalankan lagi: ./scripts/dev-init.sh"
    exit 1
fi

# Load .env buat cek default values
# shellcheck disable=SC1091
set -a; source .env; set +a

if [[ "${DB_PASSWORD:-}" == "change_me_to_strong_random_string" ]]; then
    die ".env masih pakai password default. Edit dulu DB_PASSWORD dan DB_ROOT_PASSWORD."
fi
if [[ "${AUTH_KEY:-}" == "generate-me" ]]; then
    die ".env masih pakai salt default. Generate baru dari https://roots.io/salts.html dan paste ke .env"
fi

# ------------------------------------------------------------------
# 2. Build image
# ------------------------------------------------------------------
log_info "Building images ..."
"${COMPOSE[@]}" build

# ------------------------------------------------------------------
# 3. Start containers
# ------------------------------------------------------------------
log_info "Starting containers ..."
"${COMPOSE[@]}" up -d

# ------------------------------------------------------------------
# 4. Wait for composer install (dev entrypoint runs it on first boot)
# ------------------------------------------------------------------
log_info "Waiting for composer install to complete (may take 2-5 min on first boot) ..."
for i in {1..60}; do
    if "${COMPOSE[@]}" exec -T app test -f /app/vendor/autoload.php 2>/dev/null; then
        log_ok "composer install complete"
        break
    fi
    if [[ $i -eq 60 ]]; then
        log_error "Timeout menunggu composer install. Cek log:"
        log_error "  ${COMPOSE[*]} logs app --tail 100"
        exit 1
    fi
    printf '.'
    sleep 5
done
echo

# Sync composer.lock ke host supaya bisa di-commit ke git (reproducibility).
# Kalau file di host outdated, ini juga refresh.
if "${COMPOSE[@]}" exec -T app test -f /app/composer.lock 2>/dev/null; then
    "${COMPOSE[@]}" exec -T app cat /app/composer.lock > composer.lock
    log_ok "composer.lock synced to host"
fi

# ------------------------------------------------------------------
# 5. Wait for app healthcheck
# ------------------------------------------------------------------
log_info "Waiting for app to be healthy ..."
for i in {1..30}; do
    status=$("${COMPOSE[@]}" ps app --format json | grep -o '"Health":"[^"]*"' | cut -d'"' -f4 || echo "starting")
    if [[ "${status}" == "healthy" ]]; then
        log_ok "app is healthy"
        break
    fi
    if [[ $i -eq 30 ]]; then
        log_warn "app belum healthy setelah 150s. Lanjut coba install WP tetap ya."
        break
    fi
    sleep 5
done

# ------------------------------------------------------------------
# 6. Install WordPress (idempotent)
# ------------------------------------------------------------------
WP_HOME_URL="${WP_HOME:-http://localhost}"

if "${COMPOSE[@]}" exec -T -u www-data app wp core is-installed --path=/app/web/wp 2>/dev/null; then
    log_ok "WordPress sudah terinstall — skip"
else
    log_info "Installing WordPress core ..."
    ADMIN_USER="${WP_ADMIN_USER:-owner}"
    ADMIN_PASS="${WP_ADMIN_PASSWORD:-changeme123}"
    ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

    "${COMPOSE[@]}" exec -T -u www-data app wp core install \
        --path=/app/web/wp \
        --url="${WP_HOME_URL}" \
        --title="${WP_SITE_TITLE:-WordPress Boilerplate}" \
        --admin_user="${ADMIN_USER}" \
        --admin_password="${ADMIN_PASS}" \
        --admin_email="${ADMIN_EMAIL}" \
        --skip-email

    log_ok "WordPress installed"
    log_ok "  Admin URL:      ${WP_HOME_URL}/wp/wp-login.php"
    log_ok "  Admin user:     ${ADMIN_USER}"
    log_ok "  Admin password: ${ADMIN_PASS}"

    log_info "Activating starter theme + core plugins ..."
    "${COMPOSE[@]}" exec -T -u www-data app wp theme activate starter --path=/app/web/wp || log_warn "starter theme activation failed"

    # Plugin yang di-aktifkan default (aman jalan bareng, no wizard blocker):
    # - elementor + elementskit-lite : page builder + Pro-alt (theme builder, mega menu)
    # - custom-post-type-ui + advanced-custom-fields : content modeling
    # - polylang + loco-translate : multilingual + string translation
    # - shortcodes-ultimate : ready-made shortcode library
    # - members : role & permission management
    # - wp-boilerplate-announcement : reference custom plugin
    DEFAULT_PLUGINS=(
        elementor
        elementskit-lite
        custom-post-type-ui
        advanced-custom-fields
        polylang
        loco-translate
        shortcodes-ultimate
        members
        wp-boilerplate-announcement
    )
    for plugin in "${DEFAULT_PLUGINS[@]}"; do
        "${COMPOSE[@]}" exec -T -u www-data app wp plugin activate "$plugin" --path=/app/web/wp \
            || log_warn "${plugin} activation failed (mungkin butuh setup wizard manual)"
    done
fi

# Pretty permalinks (idempotent — safe to always run). Diperlukan supaya REST API
# /wp-json/ dan wp-admin routing bekerja lewat Caddy's try_files fallback.
current_permalink=$("${COMPOSE[@]}" exec -T -u www-data app wp option get permalink_structure --path=/app/web/wp 2>/dev/null | tr -d '\r\n' || echo "")
if [[ -z "${current_permalink}" || "${current_permalink}" == "" ]]; then
    log_info "Setting pretty permalinks (/%postname%/) ..."
    "${COMPOSE[@]}" exec -T -u www-data app wp rewrite structure '/%postname%/' --hard --path=/app/web/wp >/dev/null 2>&1 || log_warn "permalink set failed"
    log_ok "permalinks set"
fi

log_ok "Setup selesai. Buka: ${WP_HOME_URL}"
