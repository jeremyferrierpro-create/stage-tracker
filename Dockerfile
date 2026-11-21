FROM php:8.5-fpm-alpine

# 1. Dépendances système nécessaires pour PHP, PostgreSQL, les images et Vite/Node
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

# 2. Configuration et installation des extensions PHP
RUN docker-php-ext-install -j$(nproc) pdo_pgsql pgsql zip bcmath

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd \
    && apk del .build-deps

# 3. Récupération de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4. Dossier de travail dans le conteneur
WORKDIR /var/www/html

# 5. Copie du code de l'application
COPY . /var/www/html

# 6. Installation des paquets PHP (sans dev) et compilation des assets front (Vite)
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build \
    && rm -rf node_modules

# 7. Droits d'écriture pour les dossiers de cache et logs de Laravel
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# 8. Migrations puis caches Laravel et lancement du serveur
#    (`migrate --force` est idempotent : il n'applique que les migrations
#    manquantes, ce qui rend le déploiement Render sûr à chaque redeploy).
CMD php artisan migrate --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}