#!/usr/bin/env bash
set -e

# Configure Apache to listen on Render's assigned $PORT (defaults to 80 if not set)
PORT="${PORT:-80}"
echo "==> Configuring Apache to listen on port ${PORT}..."
sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf || true

# Normalize Render's internal PostgreSQL database URL with full Oregon domain if needed
if [ -n "$DATABASE_URL" ] && [[ "$DATABASE_URL" == *"dpg-"* ]] && [[ "$DATABASE_URL" != *".render.com"* ]]; then
    DATABASE_URL=$(php -r '$u = getenv("DATABASE_URL"); $p = parse_url($u); if (!empty($p["host"]) && !str_contains($p["host"], ".")) { $u = str_replace($p["host"], $p["host"] . ".oregon-postgres.render.com", $u); } echo $u;')
    export DATABASE_URL
    echo "==> Normalized DATABASE_URL to use full Oregon domain..."
fi

# Ensure SQLite fallback database exists and is writable
touch /var/www/html/database/database.sqlite 2>/dev/null || touch database/database.sqlite 2>/dev/null || true
chmod -R 777 /var/www/html/database 2>/dev/null || chmod -R 777 database 2>/dev/null || true

echo "==> Running package discovery..."
php artisan package:discover --ansi || true

echo "==> Clearing cached configurations..."
php artisan config:clear || true
php artisan route:clear || true

echo "==> Running database migrations..."
php artisan migrate --force || echo "==> Notice: Migration could not complete on primary DB, fallback will handle it"

echo "==> Ensuring admin users have correct passwords..."
php artisan admin:ensure || echo "==> Notice: Admin ensure could not complete on primary DB, fallback will handle it"

echo "==> Starting Apache server on port ${PORT}..."
exec apache2-foreground
