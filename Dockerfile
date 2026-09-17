FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

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

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match the container user to the host user so bind-mounted files are not root-owned.
RUN groupmod -o -g "${GID}" www-data \
    && usermod  -o -u "${UID}" -g "${GID}" www-data

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
