FROM docker.io/library/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-req=ext-gd

FROM docker.io/library/php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libonig-dev libpng-dev libpq-dev libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mbstring pdo_pgsql pdo_sqlite zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN chmod -R a+rX /var/www/html \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs database \
    && chown -R www-data:www-data storage database
COPY docker-entrypoint.sh /usr/local/bin/hibrido-entrypoint
RUN chmod +x /usr/local/bin/hibrido-entrypoint
ENTRYPOINT ["hibrido-entrypoint"]
CMD ["apache2-foreground"]
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 CMD ["php", "/var/www/html/docker-healthcheck.php"]
