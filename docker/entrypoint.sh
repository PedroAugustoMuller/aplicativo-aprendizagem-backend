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

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"
