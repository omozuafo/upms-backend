#!/usr/bin/env bash
set -e

echo "==> Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "==> Caching config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Starting Laravel server..."
php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
