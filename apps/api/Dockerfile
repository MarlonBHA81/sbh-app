# ---- Composer deps ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---- Dev image (bind-mounted source, artisan serve) ----
FROM php:8.3-cli-alpine AS dev
RUN apk add --no-cache ffmpeg icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) bcmath exif gd intl mbstring pcntl pdo_mysql zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS
WORKDIR /app
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# ---- Production image (php-fpm + baked source) ----
FROM php:8.3-fpm-alpine AS prod
RUN apk add --no-cache ffmpeg icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev oniguruma-dev supervisor linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) bcmath exif gd intl mbstring opcache pcntl pdo_mysql zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS
WORKDIR /app
COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/supervisord.conf /etc/supervisord.conf
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
