# syntax=docker/dockerfile:1
# Production image: FrankenPHP (Caddy + PHP) running as a non-root user.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --ignore-platform-req=ext-*

FROM dunglas/frankenphp:1-php8.4 AS app
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:8080

RUN install-php-extensions intl pdo_pgsql opcache zip

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN composer dump-autoload --classmap-authoritative --no-dev \
    && APP_SECRET=build-time-only DATABASE_URL="postgresql://x:x@localhost/x?serverVersion=16" php bin/console cache:warmup \
    && rm /usr/local/bin/composer \
    && chown -R www-data:www-data var \
    && setcap -r /usr/local/bin/frankenphp || true

USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --start-period=20s CMD curl -fs http://localhost:8080/health || exit 1
CMD ["frankenphp", "php-server", "--root", "/app/public", "--listen", ":8080"]
