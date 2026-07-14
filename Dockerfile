# ─── Stage 1: PHP dependencies ────────────────────────────────────────────────
FROM composer:2.7 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ─── Stage 2: Node.js assets ──────────────────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
RUN npm ci && npm run build

# ─── Stage 3: Production image ────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS production

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpq \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    redis \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        pcntl \
        opcache \
    && docker-php-ext-enable opcache

# PHP configuration
COPY docker/php/php.ini         /usr/local/etc/php/conf.d/app.ini
COPY docker/php/opcache.ini     /usr/local/etc/php/conf.d/opcache.ini
COPY docker/nginx/default.conf  /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy application
COPY --chown=www-data:www-data . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Storage permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Laravel optimisation
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ─── Stage 4: Development ─────────────────────────────────────────────────────
FROM production AS development
RUN apk add --no-cache git && \
    docker-php-ext-install xdebug 2>/dev/null || true
ENV APP_ENV=local APP_DEBUG=true
