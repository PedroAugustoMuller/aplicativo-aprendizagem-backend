# Stages: base (PHP + extensions) → production (code baked in) → development
# (bind-mounted code, host UID). development stays LAST so the dev compose,
# which names no target, keeps building it.
FROM php:8.4-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev zip unzip \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        git curl \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql pgsql \
        zip intl bcmath mbstring pcntl opcache

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS production

COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/app.ini

# Dependencies first, so a code-only change reuses this layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh
RUN chmod +x /usr/local/bin/entrypoint.prod.sh

ENTRYPOINT ["entrypoint.prod.sh"]
CMD ["php-fpm"]

FROM base AS development

ARG UID=1000
ARG GID=1000

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Match the container user to the host user so bind-mounted files are not root-owned.
RUN groupmod -o -g "${GID}" www-data \
    && usermod  -o -u "${UID}" -g "${GID}" www-data

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
