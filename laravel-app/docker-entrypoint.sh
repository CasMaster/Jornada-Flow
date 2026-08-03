#!/bin/sh
set -eu
mkdir -p /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p /var/www/html/database
    touch "${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    chown -R www-data:www-data /var/www/html/database
fi

attempt=0
until php artisan migrate --force; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "Banco de dados indisponível após 30 tentativas." >&2
        exit 1
    fi
    sleep 2
done
php artisan config:cache
exec "$@"
