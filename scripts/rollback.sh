#!/usr/bin/env bash
# ==============================================================================
# rollback.sh — rollback ke tag image sebelumnya (di server)
#
# Usage (dari root repo di server):
#   ./scripts/rollback.sh                    # rollback ke tag di .last-deployed-tag.previous
#   ./scripts/rollback.sh sha-abc123         # rollback ke tag spesifik
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

require_cmd docker

# Detect env
if   [[ -f .env.production ]]; then ENV_FILE=".env.production"; OVERRIDE="docker-compose.prod.yml";    ENV_NAME="production"
elif [[ -f .env.staging    ]]; then ENV_FILE=".env.staging";    OVERRIDE="docker-compose.staging.yml"; ENV_NAME="staging"
else die "No .env.production or .env.staging found"
fi

: "${DEPLOY_IMAGE:?DEPLOY_IMAGE must be set (or in $ENV_FILE)}"

TARGET_TAG="${1:-}"
if [[ -z "${TARGET_TAG}" ]]; then
    if [[ -f .last-deployed-tag.previous ]]; then
        TARGET_TAG="$(cat .last-deployed-tag.previous)"
    else
        die "No target tag provided and .last-deployed-tag.previous not found"
    fi
fi

log_warn "Rolling back ${ENV_NAME} to tag: ${TARGET_TAG}"
confirm "Continue?" || die "Cancelled"

# Preserve current tag as .previous sebelum overwrite
if [[ -f .last-deployed-tag ]]; then
    cp .last-deployed-tag .last-deployed-tag.previous
fi

export DEPLOY_TAG="${TARGET_TAG}"

COMPOSE=(docker compose --env-file "${ENV_FILE}" -f docker-compose.yml -f "${OVERRIDE}")

log_info "Pulling ${DEPLOY_IMAGE}:${TARGET_TAG} ..."
docker pull "${DEPLOY_IMAGE}:${TARGET_TAG}"

log_info "Rolling out ..."
"${COMPOSE[@]}" up -d --no-deps --wait --wait-timeout 60 app

echo "${TARGET_TAG}" > .last-deployed-tag
log_ok "Rollback to ${TARGET_TAG} complete"
