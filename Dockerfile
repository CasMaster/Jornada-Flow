FROM php:8.3-apache

RUN docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite headers \
    && printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/hibrido.conf \
    && a2enconf hibrido

WORKDIR /var/www/html
COPY . .

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod 750 /var/www/html/data

ENV HIBRIDO_TIMEZONE=America/Sao_Paulo
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://localhost/login.php') === false ? 1 : 0);"
