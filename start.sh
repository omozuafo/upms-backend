#!/usr/bin/env bash
set -e

echo "==> Running package discovery..."
php artisan package:discover --ansi

echo "==> Caching config..."
php artisan config:cache

echo "==> Caching routes..."
php artisan route:cache

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Ensuring admin users have correct passwords..."
php artisan admin:ensure

echo "==> Starting Apache server..."
exec apache2-foreground
