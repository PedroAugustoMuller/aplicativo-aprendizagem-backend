#!/usr/bin/env bash
# Dumps the production database to ~/backups and keeps the last 14 days.
# Run by cron at 03:00 (installed by deploy.sh) or by hand:
#   ~/aplicativo-aprendizagem-backend/deploy/backup.sh
set -euo pipefail

cd "$(dirname "$0")/.."

dir="${BACKUP_DIR:-/home/deploy/backups}"
file="${dir}/quimica-$(date +%Y-%m-%d-%H%M).sql.gz"
mkdir -p "$dir"

# Overridable only so the script can be exercised against a local test stack.
export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

# The postgres container knows its own user and database names.
docker compose exec -T postgres \
    sh -c 'pg_dump --no-owner -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "${file}.partial"
mv "${file}.partial" "$file"

find "$dir" -name 'quimica-*.sql.gz' -mtime +13 -delete

echo "$(date -Iseconds) backup ok: ${file} ($(du -h "$file" | cut -f1))"
