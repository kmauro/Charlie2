# syntax=docker/dockerfile:1.7

FROM node:24.21.0-bookworm-slim AS frontend

WORKDIR /var/www/html

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY tsconfig.json vite.config.ts ./

RUN npm run build

FROM php:8.5.10-apache-bookworm AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        curl \
        gosu \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        zip \
    && a2enmod expires headers rewrite \
    && php -r '$required = ["bcmath", "curl", "dom", "intl", "mbstring", "Zend OPcache", "pcntl", "pdo_mysql", "zip"]; foreach ($required as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } }' \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM php-base AS vendor

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY . .
RUN composer dump-autoload \
    --classmap-authoritative \
    --no-dev \
    --no-interaction

FROM php-base AS production

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

COPY --chown=www-data:www-data --from=vendor /var/www/html /var/www/html
COPY --chown=www-data:www-data --from=frontend /var/www/html/public/build /var/www/html/public/build

RUN chmod +x /usr/local/bin/docker-entrypoint \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]
