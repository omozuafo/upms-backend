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

echo "==> Ensuring default admin accounts exist with correct passwords..."
php artisan tinker --no-interaction << 'EOF'
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Fix/create super admin
$sa = User::where('email', 'superadmin@upms.com')->first();
if ($sa) {
    $sa->password = Hash::make('asdfghj69.');
    $sa->save();
    echo "Super Admin password reset.\n";
} else {
    User::create([
        'name' => 'Super Admin',
        'email' => 'superadmin@upms.com',
        'password' => Hash::make('asdfghj69.'),
        'role' => 'super_admin',
    ]);
    echo "Super Admin created.\n";
}

// Fix/create admin
$admin = User::where('email', 'admin@example.com')->first();
if ($admin) {
    $admin->password = Hash::make('password');
    $admin->save();
    echo "Admin password reset.\n";
} else {
    User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);
    echo "Admin created.\n";
}
EOF

echo "==> Starting Apache server..."
exec apache2-foreground
