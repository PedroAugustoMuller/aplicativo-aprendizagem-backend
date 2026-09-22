#!/bin/sh
set -e

LOCK_HASH_FILE="storage/framework/.composer-lock-hash"
CURRENT_HASH="$(md5sum composer.lock 2>/dev/null | cut -d' ' -f1 || echo none)"

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ ! -f "$LOCK_HASH_FILE" ] || [ "$(cat "$LOCK_HASH_FILE")" != "$CURRENT_HASH" ]; then
    echo "composer.lock changed - installing dependencies"
    composer install --prefer-dist --no-interaction
    # composer runs as root here; without this, vendor/ lands root-owned in the
    # bind mount and `exec -u www-data app composer require` fails on the next task.
    chown -R www-data:www-data vendor composer.lock
    echo "$CURRENT_HASH" > "$LOCK_HASH_FILE"
fi

php artisan config:clear

# Only the server refuses to start: one-off commands such as
# `docker compose run --rm app php artisan key:generate` must still work,
# since that is how the key gets created in the first place.
if [ "$1" = "php-fpm" ] && [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty in this container's environment - refusing to start php-fpm." >&2
    echo "Without it, anything that touches the encrypter fails with MissingAppKeyException." >&2
    echo "Fix: docker compose run --rm --no-deps app php artisan key:generate" >&2
    echo "     UID=\$(id -u) GID=\$(id -g) docker compose up -d" >&2
    echo "If .env already has a key, this container predates it: the 'up -d' alone recreates it." >&2
    exit 1
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"
