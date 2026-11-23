# Single image for Railway: the Vue SPA is built and served by Laravel from
# the same origin, so the frontend's relative `/api` calls need no CORS setup.

# --- 1. Frontend build -------------------------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# --- 2. PHP dependencies -----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
      --no-interaction --no-progress --ignore-platform-reqs

# --- 3. Runtime --------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_mysql bcmath intl gd zip opcache pcntl \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'upload_max_filesize=10M\npost_max_size=12M\nmemory_limit=256M\n' \
      > "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY backend/ ./
COPY --from=vendor /app/vendor ./vendor
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && php artisan package:discover --ansi

# SPA: hashed assets are served as static files; index.html is returned by
# the catch-all route in routes/web.php.
COPY --from=frontend /frontend/dist/assets ./public/assets
COPY --from=frontend /frontend/dist/favicon.ico ./public/favicon.ico
COPY --from=frontend /frontend/dist/index.html ./resources/spa/index.html

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

ENV PORT=8080
EXPOSE 8080
CMD ["start"]
