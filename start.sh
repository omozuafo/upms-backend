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

echo "==> Probing database connection..."
USE_SQLITE=0
if [ "$DB_CONNECTION" = "pgsql" ] || [ -n "$DATABASE_URL" ]; then
    php -r '
        $url = getenv("DATABASE_URL") ?: getenv("DB_URL");
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: 5432;
        $db = getenv("DB_DATABASE") ?: "upms_db";
        $user = getenv("DB_USERNAME") ?: "upms";
        $pass = getenv("DB_PASSWORD") ?: "";
        if ($url) {
            $p = parse_url($url);
            if (!empty($p["host"])) $host = $p["host"];
            if (!empty($p["port"])) $port = $p["port"];
            if (!empty($p["path"])) $db = ltrim($p["path"], "/");
            if (!empty($p["user"])) $user = $p["user"];
            if (isset($p["pass"])) $pass = $p["pass"];
        }
        if (!$host) { exit(1); }
        try {
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db;connect_timeout=3", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
            echo "==> Connected to PostgreSQL successfully." . PHP_EOL;
            exit(0);
        } catch (\Throwable $e) {
            echo "==> PostgreSQL connection test failed: " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    ' || USE_SQLITE=1
fi

if [ "$USE_SQLITE" = "1" ] || [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    echo "==> Using SQLite database engine..."
    export DB_CONNECTION=sqlite
    export SESSION_DRIVER=file
    export CACHE_STORE=file
    export QUEUE_CONNECTION=sync
fi

echo "==> Running database migrations..."
php artisan migrate --force || echo "==> Notice: Migration output logged above"

echo "==> Ensuring admin users have correct passwords..."
php artisan admin:ensure || echo "==> Notice: Admin ensure output logged above"

echo "==> Starting Apache server on port ${PORT}..."
exec apache2-foreground
