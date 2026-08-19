#!/usr/bin/env bash
# ==============================================================================
# smoke-test.sh — boot image sekilas + curl healthz
#
# Dipakai di CI (plugin-update.yml) untuk verifikasi image tidak broken
# setelah composer update.
#
# Usage: ./scripts/smoke-test.sh <image-tag>
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

IMAGE="${1:?Usage: $0 <image-tag>}"

require_cmd docker curl

CONTAINER="wp-smoke-$$"
NETWORK="wp-smoke-net-$$"

cleanup() {
    docker rm -f "${CONTAINER}"      >/dev/null 2>&1 || true
    docker rm -f "${CONTAINER}-db"   >/dev/null 2>&1 || true
    docker network rm "${NETWORK}"   >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create "${NETWORK}" >/dev/null

log_info "Starting ephemeral MariaDB ..."
docker run -d --rm --name "${CONTAINER}-db" \
    --network "${NETWORK}" \
    -e MARIADB_DATABASE=wordpress \
    -e MARIADB_USER=wordpress \
    -e MARIADB_PASSWORD=test_pass \
    -e MARIADB_ROOT_PASSWORD=test_root \
    mariadb:11.4 >/dev/null

log_info "Waiting for DB to be healthy ..."
for _ in {1..30}; do
    if docker exec "${CONTAINER}-db" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

log_info "Starting app container from ${IMAGE} ..."
docker run -d --rm --name "${CONTAINER}" \
    --network "${NETWORK}" \
    -p 18080:80 \
    -e WP_ENV=production \
    -e WP_HOME=http://localhost:18080 \
    -e WP_SITEURL=http://localhost:18080/wp \
    -e DB_HOST="${CONTAINER}-db" \
    -e DB_NAME=wordpress \
    -e DB_USER=wordpress \
    -e DB_PASSWORD=test_pass \
    -e AUTH_KEY=smoke -e SECURE_AUTH_KEY=smoke -e LOGGED_IN_KEY=smoke -e NONCE_KEY=smoke \
    -e AUTH_SALT=smoke -e SECURE_AUTH_SALT=smoke -e LOGGED_IN_SALT=smoke -e NONCE_SALT=smoke \
    -e SERVER_NAME=":80" \
    "${IMAGE}" >/dev/null

log_info "Waiting for /healthz ..."
HEALTHY=0
for i in {1..20}; do
    if curl -fsS --max-time 3 http://127.0.0.1:18080/healthz >/dev/null 2>&1; then
        HEALTHY=1
        log_ok "healthz OK on attempt ${i}"
        break
    fi
    sleep 2
done

if [[ ${HEALTHY} -eq 0 ]]; then
    log_error "Smoke test FAILED — container tidak respons di /healthz"
    log_info "Container logs:"
    docker logs "${CONTAINER}" 2>&1 | tail -n 100 || true
    exit 1
fi

log_info "Verifying WP-CLI can list plugins ..."
if docker exec "${CONTAINER}" wp plugin list --path=/app/web/wp --format=count 2>&1 | tail -n1; then
    log_ok "wp plugin list OK"
else
    log_warn "wp plugin list gagal — DB mungkin belum di-install. Non-fatal untuk smoke test."
fi

log_ok "Smoke test PASSED"
