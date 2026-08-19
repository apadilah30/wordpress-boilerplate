#!/usr/bin/env bash
# ==============================================================================
# deploy.sh — dijalankan di server (bukan CI runner)
#
# Alur:
# 1. Pre-deploy DB backup (safety net)
# 2. Simpan tag lama untuk rollback
# 3. docker compose pull image baru
# 4. docker compose up -d --no-deps app  (zero-downtime via healthcheck)
# 5. wp core update-db + cache flush
# 6. HTTP healthcheck loop
# 7. Kalau health check gagal → auto rollback ke tag lama
#
# ENV (di-set oleh CI atau manual):
#   DEPLOY_IMAGE (mis. ghcr.io/OWNER/REPO)
#   DEPLOY_TAG   (mis. sha-abc123)
#
# Compose file yang dipakai auto-detect: kalau file .env.production ada → prod,
# else staging.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd docker curl

: "${DEPLOY_IMAGE:?DEPLOY_IMAGE not set}"
: "${DEPLOY_TAG:?DEPLOY_TAG not set}"

# Detect environment
if [[ -f .env.production ]]; then
    ENV_FILE=".env.production"
    COMPOSE_OVERRIDE="docker-compose.prod.yml"
    ENV_NAME="production"
elif [[ -f .env.staging ]]; then
    ENV_FILE=".env.staging"
    COMPOSE_OVERRIDE="docker-compose.staging.yml"
    ENV_NAME="staging"
else
    die "No .env.production or .env.staging found in $(pwd)"
fi

log_info "Deploy target: ${ENV_NAME}"
log_info "Image: ${DEPLOY_IMAGE}:${DEPLOY_TAG}"

export DEPLOY_IMAGE DEPLOY_TAG

COMPOSE=(docker compose --env-file "${ENV_FILE}" -f docker-compose.yml -f "${COMPOSE_OVERRIDE}")

# ------------------------------------------------------------------
# 1. Pre-deploy DB backup
# ------------------------------------------------------------------
log_info "Taking pre-deploy DB backup ..."
if ! "${COMPOSE[@]}" exec -T backup /usr/local/bin/backup.sh; then
    log_warn "Pre-deploy backup gagal — lanjut deploy (tapi rollback lebih berisiko)"
fi

# ------------------------------------------------------------------
# 2. Simpan tag lama untuk rollback
# ------------------------------------------------------------------
PREV_TAG_FILE=".last-deployed-tag"
PREV_TAG="$(cat "${PREV_TAG_FILE}" 2>/dev/null || echo "")"
log_info "Previous tag: ${PREV_TAG:-<none>}"

# ------------------------------------------------------------------
# 3. Pull image baru
# ------------------------------------------------------------------
log_info "Pulling ${DEPLOY_IMAGE}:${DEPLOY_TAG} ..."
docker pull "${DEPLOY_IMAGE}:${DEPLOY_TAG}"

# ------------------------------------------------------------------
# 4. Deploy — up -d --no-deps app (jangan restart DB/Redis)
# ------------------------------------------------------------------
log_info "Rolling out app ..."
"${COMPOSE[@]}" up -d --no-deps --wait --wait-timeout 60 app

# ------------------------------------------------------------------
# 5. Run WP-CLI migrasi & cache flush
# ------------------------------------------------------------------
log_info "Running wp core update-db + cache flush ..."
"${COMPOSE[@]}" exec -T -u www-data app wp core update-db --path=/app/web/wp || log_warn "update-db warning (ok kalau baru first deploy)"
"${COMPOSE[@]}" exec -T -u www-data app wp cache flush --path=/app/web/wp    || true

# ------------------------------------------------------------------
# 6. HTTP healthcheck loop
# ------------------------------------------------------------------
log_info "Health check ..."
HEALTHCHECK_URL="http://127.0.0.1/healthz"
HEALTHY=0
for i in {1..12}; do
    if curl -fsS --max-time 5 "${HEALTHCHECK_URL}" >/dev/null; then
        HEALTHY=1
        break
    fi
    log_info "  attempt ${i}/12 — waiting 5s"
    sleep 5
done

if [[ ${HEALTHY} -eq 0 ]]; then
    log_error "Health check FAILED — attempting rollback"
    if [[ -n "${PREV_TAG}" ]]; then
        DEPLOY_TAG="${PREV_TAG}" "${COMPOSE[@]}" up -d --no-deps --wait --wait-timeout 60 app
        log_warn "Rolled back to ${PREV_TAG}"
        exit 1
    fi
    log_error "No previous tag to rollback to. Manual intervention needed."
    exit 1
fi

# ------------------------------------------------------------------
# 7. Save current tag untuk rollback berikutnya
# ------------------------------------------------------------------
echo "${DEPLOY_TAG}" > "${PREV_TAG_FILE}"
log_ok "Deploy ${DEPLOY_TAG} to ${ENV_NAME} completed"
