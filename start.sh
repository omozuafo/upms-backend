#!/usr/bin/env bash
set -e

# Configure Apache to listen on Render's assigned $PORT (defaults to 80 if not set)
PORT="${PORT:-80}"
echo "==> Configuring Apache to listen on port ${PORT}..."
sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf || true

echo "==> Running package discovery..."
php artisan package:discover --ansi || true

echo "==> Caching config..."
php artisan config:cache || true

echo "==> Caching routes..."
php artisan route:cache || true

echo "==> Running database migrations..."
php artisan migrate --force || echo "==> Notice: Migration could not complete or DB not yet ready"

echo "==> Ensuring admin users have correct passwords..."
php artisan admin:ensure || echo "==> Notice: Admin ensure could not complete"

echo "==> Starting Apache server on port ${PORT}..."
exec apache2-foreground
