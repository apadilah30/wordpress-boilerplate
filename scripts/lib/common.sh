#!/usr/bin/env bash
# ==============================================================================
# common.sh — fungsi bash reusable untuk semua script di scripts/*
#
# Pattern pakai:
#   #!/usr/bin/env bash
#   set -euo pipefail
#   SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
#   # shellcheck source=lib/common.sh
#   source "${SCRIPT_DIR}/lib/common.sh"
# ==============================================================================

# --- Colors (tty only) ---
if [[ -t 1 ]]; then
    _c_reset=$'\033[0m'
    _c_red=$'\033[31m'
    _c_green=$'\033[32m'
    _c_yellow=$'\033[33m'
    _c_blue=$'\033[34m'
else
    _c_reset='' _c_red='' _c_green='' _c_yellow='' _c_blue=''
fi

log_info()  { printf '%s[INFO]%s  %s\n'  "${_c_blue}"   "${_c_reset}" "$*"; }
log_ok()    { printf '%s[OK]%s    %s\n'  "${_c_green}"  "${_c_reset}" "$*"; }
log_warn()  { printf '%s[WARN]%s  %s\n'  "${_c_yellow}" "${_c_reset}" "$*" >&2; }
log_error() { printf '%s[ERR]%s   %s\n'  "${_c_red}"    "${_c_reset}" "$*" >&2; }

die() {
    log_error "$*"
    exit 1
}

# require_cmd curl git ...
require_cmd() {
    local missing=0
    for cmd in "$@"; do
        if ! command -v "${cmd}" >/dev/null 2>&1; then
            log_error "Missing required command: ${cmd}"
            missing=1
        fi
    done
    [[ ${missing} -eq 0 ]] || exit 1
}

# require_env VAR1 VAR2 ...
require_env() {
    local missing=0
    for var in "$@"; do
        if [[ -z "${!var:-}" ]]; then
            log_error "Missing required env var: ${var}"
            missing=1
        fi
    done
    [[ ${missing} -eq 0 ]] || exit 1
}

# wait_for_tcp <host> <port> [timeout_sec=30]
wait_for_tcp() {
    local host="$1" port="$2" timeout="${3:-30}"
    log_info "Waiting for ${host}:${port} (timeout ${timeout}s) ..."
    for _ in $(seq 1 "${timeout}"); do
        if (echo >/dev/tcp/"${host}"/"${port}") 2>/dev/null; then
            log_ok "${host}:${port} is reachable"
            return 0
        fi
        sleep 1
    done
    die "Timeout waiting for ${host}:${port}"
}

# confirm "Prompt?" — return 0 if yes, 1 if no
confirm() {
    local prompt="${1:-Are you sure?} [y/N] "
    read -r -p "${prompt}" reply
    [[ "${reply}" =~ ^[Yy]$ ]]
}

# Repo root (relative ke script yang source-nya)
repo_root() {
    git rev-parse --show-toplevel 2>/dev/null || pwd
}
