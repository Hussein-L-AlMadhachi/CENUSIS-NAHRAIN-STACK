# Backend Dockerfile, PHP 8.3 FPM + nginx on port 3000
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        nginx \
        composer \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip mbstring

WORKDIR /app

# composer autoloader (phpspreadsheet used by the xlsx routes)
COPY backend/composer.json ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --no-progress

COPY backend/public ./public
COPY backend/src ./src

COPY backend/docker/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 3000

# php-fpm in background, nginx in foreground
CMD ["sh", "-c", "php-fpm & exec nginx -g 'daemon off;'"]
