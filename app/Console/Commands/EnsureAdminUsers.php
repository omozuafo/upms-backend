<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class EnsureAdminUsers extends Command
{
    protected $signature = 'admin:ensure';
    protected $description = 'Ensure default admin users exist with correct passwords';

    public function handle()
    {
        $this->info('Ensuring admin users exist with correct passwords...');

        $admins = [
            [
                'name'  => 'Super Admin',
                'email' => 'superadmin@upms.com',
                'password' => Hash::make('asdfghj69.'),
                'role'  => 'super_admin',
            ],
            [
                'name'  => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role'  => 'admin',
            ],
        ];

        foreach ($admins as $admin) {
            $existing = DB::table('users')->where('email', $admin['email'])->first();

            if ($existing) {
                DB::table('users')
                    ->where('email', $admin['email'])
                    ->update(['password' => $admin['password']]);
                $this->info("Updated password for: {$admin['email']}");
            } else {
                DB::table('users')->insert([
                    'name'       => $admin['name'],
                    'email'      => $admin['email'],
                    'password'   => $admin['password'],
                    'role'       => $admin['role'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("Created user: {$admin['email']}");
            }
        }

        $this->info('Done!');
        return Command::SUCCESS;
    }
}
