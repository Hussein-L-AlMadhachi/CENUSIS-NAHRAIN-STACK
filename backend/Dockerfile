# Backend Dockerfile, PHP 8.3 FPM + nginx on port 4000
FROM php:8.3-fpm-alpine

# Composer from the official image, so it runs on THIS PHP (8.3)
# instead of pulling in Alpine's system PHP via apk
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# nginx + build deps for the extensions we compile
RUN apk add --no-cache \
        nginx \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev

# Only extensions NOT already built into the official image.
# (mbstring, dom, xml, xmlwriter, simplexml, fileinfo, iconv are already included)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip

WORKDIR /app

# Install dependencies first so this layer is cached between code changes.
# composer.lock* is optional: copied if it exists, skipped if not.
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --no-progress --no-autoloader

# App code
COPY backend/public ./public
COPY backend/src ./src

# Build the optimized autoloader now that src/ is present
RUN composer dump-autoload --optimize --no-dev

COPY backend/docker/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 4000

# php-fpm in background, nginx in foreground
CMD ["sh", "-c", "php-fpm -D && exec nginx -g 'daemon off;'"]