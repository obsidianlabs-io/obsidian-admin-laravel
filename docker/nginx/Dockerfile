FROM nginx:1.27-alpine

RUN apk upgrade --no-cache

WORKDIR /var/www/html

COPY public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
