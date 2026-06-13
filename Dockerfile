FROM php:8.3-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    nodejs \
    npm \
    postgresql-dev \
    linux-headers \
    oniguruma-dev \
    libzip-dev \
    icu-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        mbstring \
        zip \
        intl \
        opcache \
        pcntl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP production config
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/custom.ini"
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

# Install PHP dependencies (production only)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Install JS dependencies and build assets
COPY package.json package-lock.json ./
RUN npm ci --prefer-offline

# Copy application code
COPY . .

# Build-time frontend config (VITE_ vars are baked into the JS bundle)
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

# Build frontend assets
# WAYFINDER_SKIP_TYPES skips `php artisan wayfinder:generate` during Docker build
# (no .env / DB available at build time — types are pre-generated in the repo)
RUN WAYFINDER_SKIP_TYPES=true npm run build

# Run post-install scripts now that code is present
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Cache views only (config/route cache must run at runtime with real env vars)
RUN php artisan view:cache --no-interaction 2>/dev/null || true

EXPOSE 80

# At container start: warm Laravel caches, then run supervisor.
# Migrations are handled by deploy.sh after services are up.
CMD sh -c "php artisan config:cache || true; php artisan route:cache || true; /usr/bin/supervisord -c /etc/supervisord.conf"
