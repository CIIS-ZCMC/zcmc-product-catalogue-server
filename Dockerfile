# ========================================================
# Stage 1: Build Production Dependencies with Composer
# ========================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ========================================================
# Stage 2: Production Runtime with FrankenPHP + Octane + Caddy
# ========================================================
FROM dunglas/frankenphp:1-php8.3-bookworm AS runner

# Install essential PHP extensions for Laravel Octane
RUN install-php-extensions \
    pdo_mysql \
    pdo_pgsql \
    sqlite3 \
    pdo_sqlite \
    gd \
    intl \
    zip \
    opcache \
    pcntl \
    redis

# Set server and environment defaults
ENV SERVER_NAME=":80" \
    APP_BASE_PATH="/app" \
    APP_PUBLIC_PATH="/app/public" \
    FRANKENPHP_CONFIG="worker /app/public/frankenphp-worker.php" \
    OCTANE_SERVER="frankenphp"

WORKDIR /app

# Copy application files
COPY . /app

# Copy production vendor directory from vendor stage
COPY --from=vendor /app/vendor /app/vendor

# Copy Caddyfile configuration
COPY Caddyfile /etc/caddy/Caddyfile

# Prepare storage directories and file permissions
RUN mkdir -p /app/storage/logs \
             /app/storage/framework/cache \
             /app/storage/framework/sessions \
             /app/storage/framework/views \
             /app/bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 80 443

# Start Laravel Octane on FrankenPHP with Caddy
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--caddyfile=/etc/caddy/Caddyfile", "--host=0.0.0.0", "--port=80"]
