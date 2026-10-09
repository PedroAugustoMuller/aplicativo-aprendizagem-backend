#!/usr/bin/env bash
# Deploys (or updates) the API on the VM. Run as `deploy` from anywhere:
#   ~/aplicativo-aprendizagem-backend/deploy/deploy.sh
# Steps: pull, refresh Cloudflare ranges, build, start, migrate, wait for
# /up, (re)install the backup cron. Safe to run again after a failure.
set -euo pipefail

cd "$(dirname "$0")/.."

# Overridable only so the scripts can be exercised against a local test stack.
export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
compose=(docker compose)

if [ ! -f .env ]; then
    echo ".env missing - copy .env.production.example and fill it (docs/DEPLOY.md, step 5)" >&2
    exit 1
fi

# Read single keys instead of sourcing .env: it is not guaranteed to be valid shell.
env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' || true; }
ips_file="$(env_value CLOUDFLARE_IPS_FILE)"
: "${ips_file:?CLOUDFLARE_IPS_FILE is not set in .env}"
https_port="$(env_value HTTPS_PORT)"
https_port="${https_port:-443}"

echo "==> git pull"
git pull --ff-only

echo "==> cloudflare ranges"
if ! bash deploy/cloudflare-ips.sh "$ips_file"; then
    if [ -f "$ips_file" ]; then
        echo "warning: could not refresh Cloudflare ranges - keeping ${ips_file}" >&2
    else
        echo "warning: could not fetch Cloudflare ranges - using the committed copy" >&2
        cp docker/nginx/cloudflare-ips.conf "$ips_file"
    fi
fi

echo "==> build and start"
"${compose[@]}" build --pull
"${compose[@]}" up -d --remove-orphans
# nginx's config and Cloudflare list are single-file bind mounts, pinned to the
# inode that existed when the container started; git pull and cloudflare-ips.sh
# replace those files (new inode), so a reload would re-read the OLD content.
# A restart re-mounts them (and re-resolves app's address).
"${compose[@]}" restart nginx

echo "==> migrate"
"${compose[@]}" exec -T -u www-data app php artisan migrate --force

echo "==> health"
status=000
for _ in $(seq 1 30); do
    status="$(curl -sk -o /dev/null -w '%{http_code}' "https://127.0.0.1:${https_port}/up" || true)"
    [ "$status" = "200" ] && break
    sleep 2
done
if [ "$status" != "200" ]; then
    echo "/up answered ${status} after 60 s - last app logs:" >&2
    "${compose[@]}" logs --tail 50 app nginx >&2
    exit 1
fi

echo "==> backup cron"
line="0 3 * * * $(pwd)/deploy/backup.sh >> /home/deploy/backups/backup.log 2>&1"
( crontab -l 2>/dev/null | grep -v 'deploy/backup.sh' || true; echo "$line" ) | crontab -

echo "==> deployed $(git rev-parse --short HEAD)"
