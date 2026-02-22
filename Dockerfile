FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-req=ext-pcntl \
    --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache \
    bash \
    fcgi \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip \
    autoconf \
    g++ \
    make \
    && docker-php-ext-install \
    bcmath \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm", "-F"]

