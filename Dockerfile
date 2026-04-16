FROM composer:2 AS composer-bin

FROM php:8.4-fpm-alpine AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apk upgrade --no-cache \
    && apk add --no-cache \
    bash \
    fcgi \
    icu-libs \
    libpq \
    libzip \
    sqlite-libs \
    unzip \
    zip \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    icu-dev \
    libzip-dev \
    linux-headers \
    oniguruma-dev \
    postgresql-dev \
    sqlite-dev \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    opcache \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    sockets \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["/usr/local/bin/entrypoint"]

FROM php-base AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM php-base AS app-dev

COPY . /var/www/html

RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm", "-F"]

FROM php-base AS app

COPY --from=vendor /app /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm", "-F"]
