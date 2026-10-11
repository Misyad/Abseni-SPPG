# -------------------------------------------------------------
# Backend Laravel: reproducible CI target + Apache release image
# -------------------------------------------------------------
FROM php:8.2-apache-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        default-mysql-client \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install bcmath gd intl opcache pdo_mysql pdo_sqlite zip \
    && a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

FROM base AS testing
RUN docker-php-ext-install pcntl
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        storage/test-results \
    && chown -R www-data:www-data storage bootstrap/cache

FROM base AS production
ARG APP_VERSION=local
ARG VCS_REF=unknown
LABEL org.opencontainers.image.title="Absensi SPPG Backend" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${VCS_REF}"

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --retries=3 CMD curl --fail --silent http://localhost/up || exit 1

# Named volumes can retain old ownership; repair writable directory roots only.
CMD ["sh", "-c", "mkdir -p storage/app/private storage/framework storage/logs bootstrap/cache && chown www-data:www-data storage storage/app storage/app/private storage/framework storage/logs bootstrap/cache && exec apache2-foreground"]
