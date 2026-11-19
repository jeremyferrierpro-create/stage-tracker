FROM php:8.5-fpm-alpine

# Dépendances système
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    nodejs \
    npm

# Extensions PHP requises
RUN docker-php-ext-configure gd --with-freetype --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd \
        zip \
        opcache

# Installation Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copie des fichiers projet
COPY . /var/www/html

# Installation des dépendances PHP et compilation des assets Vite
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && rm -rf node_modules

# Permissions stockage
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Démarrage avec migrations automatiques et mise en cache
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
