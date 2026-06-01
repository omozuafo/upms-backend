<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if super admin already exists
        $superAdmin = User::where('email', 'superadmin@upms.com')->first();

        if (!$superAdmin) {
            User::create([
                'name' => 'Super Admin',
                'email' => 'superadmin@upms.com',
                'password' => Hash::make('asdfghj69.'),
                'role' => 'super_admin',
            ]);

            $this->command->info('Super Admin created successfully!');
        } else {
            $this->command->info('Super Admin already exists.');
        }
    }
}
