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

        $superAdmin = [
            'name'       => 'Super Admin',
            'username'   => 'figuriad',
            'email'      => 'figuriad@gmail.com',
            'password'   => Hash::make('figuriad@2314'),
            'role'       => 'super_admin',
        ];

        $existing = DB::table('users')->where('email', $superAdmin['email'])->first();

        if ($existing) {
            DB::table('users')
                ->where('email', $superAdmin['email'])
                ->update([
                    'password'   => $superAdmin['password'],
                    'role'       => $superAdmin['role'],
                    'username'   => $superAdmin['username'] ?? $existing->username,
                    'updated_at' => now(),
                ]);
            $this->info("Updated superadmin credentials for: {$superAdmin['email']}");
        } else {
            DB::table('users')->insert([
                'name'       => $superAdmin['name'],
                'username'   => $superAdmin['username'],
                'email'      => $superAdmin['email'],
                'password'   => $superAdmin['password'],
                'role'       => $superAdmin['role'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("Created superadmin user: {$superAdmin['email']}");
        }

        $this->info('Done!');
        return Command::SUCCESS;
    }
}
