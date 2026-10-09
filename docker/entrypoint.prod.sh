#!/bin/sh
# Production entrypoint. Unlike entrypoint.sh it never installs dependencies
# (they are in the image) and never migrates (deploy/deploy.sh does that
# explicitly, so a failed migration is seen, not buried in a restart loop).
set -eu

if [ "${1:-}" = "php-fpm" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY is empty - refusing to start php-fpm. See docs/DEPLOY.md, step 5." >&2
        exit 1
    fi

    php artisan optimize
    # optimize ran as root: hand its caches (and any log line it wrote) back to
    # php-fpm's workers, or the first request that writes a log is a 500.
    chown -R www-data:www-data storage bootstrap/cache
fi

exec "$@"
