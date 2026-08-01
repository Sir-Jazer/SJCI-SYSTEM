# =============================================================================
# SJCI SYSTEM — container image for a quick Railway (or any Docker host) deploy.
# Self-contained: PHP 8.3 + all extensions Laravel/Filament need + SQLite.
# The admin panel uses Filament's own compiled assets, so no Node build is needed.
# =============================================================================
# PHP 8.4: the locked dependencies (Symfony 8.1) require >= 8.4, even though
# composer.json's floor is ^8.3.
FROM php:8.4-cli-bookworm

# --- System libraries + PHP extensions ---------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libsqlite3-dev libonig-dev libicu-dev libzip-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_sqlite mbstring bcmath intl zip gd exif \
    && rm -rf /var/lib/apt/lists/*

# --- Composer ----------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first (better build caching). Skip scripts here — package
# discovery runs at startup once the environment is present.
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-progress

# App source.
COPY . .

# Make the startup script runnable.
RUN chmod +x docker/railway-entrypoint.sh

# Railway provides $PORT; the entrypoint binds to it.
CMD ["sh", "docker/railway-entrypoint.sh"]