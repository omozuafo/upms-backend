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
        $superAdmin = User::where('email', 'figuriad@gmail.com')->first();

        if (!$superAdmin) {
            User::create([
                'name' => 'Super Admin',
                'username' => 'figuriad',
                'email' => 'figuriad@gmail.com',
                'password' => Hash::make('figuriad@2314'),
                'role' => 'super_admin',
            ]);

            $this->command->info('Super Admin created successfully!');
        } else {
            $superAdmin->update([
                'password' => Hash::make('figuriad@2314'),
                'role' => 'super_admin',
            ]);
            $this->command->info('Super Admin updated successfully!');
        }
    }
}
