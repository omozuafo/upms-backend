<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $superAdmin = DB::table('users')->where('email', 'figuriad@gmail.com')->first();

        if ($superAdmin) {
            DB::table('users')
                ->where('email', 'figuriad@gmail.com')
                ->update([
                    'password'   => Hash::make('figuriad@2314'),
                    'role'       => 'super_admin',
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('users')->insert([
                'name'       => 'Super Admin',
                'username'   => 'figuriad',
                'email'      => 'figuriad@gmail.com',
                'password'   => Hash::make('figuriad@2314'),
                'role'       => 'super_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep data intact on rollback
    }
};
