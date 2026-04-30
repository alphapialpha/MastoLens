# Stage 1: Build frontend assets
FROM node:22-alpine3.22 AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# Stage 2: Install PHP dependencies
FROM composer:2.9 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize

# Stage 3: Production PHP-FPM image
FROM php:8.4-fpm-alpine3.22 AS app

# Install system dependencies
RUN apk add --no-cache \
    mariadb-client \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-install \
    pdo_mysql \
    zip \
    intl \
    mbstring \
    pcntl \
    bcmath \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

# Copy application code
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY --from=vendor /app/bootstrap/cache/packages.php ./bootstrap/cache/packages.php

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Bake crontab into image so the host file is never mutated at runtime
COPY docker/scheduler/crontab /etc/crontabs/www-data
RUN chown root:root /etc/crontabs/www-data && chmod 600 /etc/crontabs/www-data

# PHP-FPM config: listen on port 9000 (default)
EXPOSE 9000

CMD ["php-fpm"]

# Stage 4: Production Nginx image
FROM nginx:1.30-alpine3.23 AS web

COPY --from=frontend /app/public/build /var/www/html/public/build
COPY public/index.php /var/www/html/public/index.php
COPY public/favicon.ico /var/www/html/public/favicon.ico
COPY public/robots.txt /var/www/html/public/robots.txt
COPY public/images /var/www/html/public/images

COPY docker/nginx/entrypoint.sh /entrypoint.sh
COPY docker/nginx/http.conf.template /etc/nginx/templates/http.conf.template
COPY docker/nginx/https.conf.template /etc/nginx/templates/https.conf.template
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
