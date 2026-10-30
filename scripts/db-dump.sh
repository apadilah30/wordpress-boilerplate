#!/usr/bin/env bash
# ==============================================================================
# db-dump.sh — Dump database untuk deployment ke server
#
# Usage:
#   ./scripts/db-dump.sh [output_file]
#
# Jika output_file tidak diberikan, akan generate:
#   backups/db-dump-YYYY-MM-DD_HHMMSS.sql.gz
# ==============================================================================

set -euo pipefail

# Load environment variables
if [[ -f .env ]]; then
    set -a
    source .env
    set +a
fi

# Default output file
TIMESTAMP=$(date +%Y-%m-%d_%H%M%S)
OUTPUT_FILE="${1:-backups/db-dump-${TIMESTAMP}.sql.gz}"

# Create backups directory
mkdir -p "$(dirname "${OUTPUT_FILE}")"

# Database credentials from .env
DB_HOST="${DB_HOST:-db}"
DB_NAME="${DB_NAME:?DB_NAME not set}"
DB_USER="${DB_USER:?DB_USER not set}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD not set}"

echo "=== Database Dump ==="
echo "Database: ${DB_NAME}"
echo "Host: ${DB_HOST}"
echo "Output: ${OUTPUT_FILE}"
echo ""

# Check if running in Docker context
if docker ps &>/dev/null && docker compose ps &>/dev/null; then
    echo "Detected Docker environment..."
    
    # Check if container is running
    if ! docker compose ps db --format '{{.State}}' | grep -q 'running'; then
        echo "ERROR: Database container is not running."
        echo "Start containers first: docker compose up -d db"
        exit 1
    fi
    
    echo "Dumping database via Docker..."
    
    # Dump via docker compose exec
    docker compose exec -T db mysqldump \
        --user="${DB_USER}" \
        --password="${DB_PASSWORD}" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" | gzip -9 > "${OUTPUT_FILE}"
else
    echo "Dumping database directly..."
    
    # Direct mysqldump (if mysql client available locally)
    if ! command -v mysqldump &>/dev/null; then
        echo "ERROR: mysqldump not found."
        echo "Install mysql client or run via Docker."
        exit 1
    fi
    
    MYSQL_PWD="${DB_PASSWORD}" mysqldump \
        --host="${DB_HOST}" \
        --user="${DB_USER}" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" | gzip -9 > "${OUTPUT_FILE}"
fi

# Verify dump
if [[ -f "${OUTPUT_FILE}" ]]; then
    SIZE=$(du -h "${OUTPUT_FILE}" | cut -f1)
    echo ""
    echo "✓ Dump completed successfully!"
    echo "  File: ${OUTPUT_FILE}"
    echo "  Size: ${SIZE}"
    echo ""
    echo "To restore on server:"
    echo "  gunzip -c ${OUTPUT_FILE##*/} | mysql -u [user] -p [database]"
else
    echo "ERROR: Dump file not created"
    exit 1
fi
