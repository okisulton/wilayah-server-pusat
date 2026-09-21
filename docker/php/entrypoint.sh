#!/bin/bash
set -e

if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo ">> Installing Composer dependencies for Laravel 11..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f /var/www/html/.env ]; then
    echo ">> Generating .env file from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
    php artisan key:generate --force
fi

echo ">> Ensuring storage and cache permissions..."
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache || true

echo ">> Running database migrations..."
php artisan migrate --force

echo ">> Starting application server..."
exec "$@"