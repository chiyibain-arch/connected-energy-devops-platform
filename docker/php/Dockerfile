FROM php:8.3-fpm-bookworm

ARG HOST_UID=501
ARG HOST_GID=20

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* \
    && groupmod --non-unique --gid "${HOST_GID}" www-data \
    && usermod --non-unique --uid "${HOST_UID}" --gid "${HOST_GID}" www-data

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
