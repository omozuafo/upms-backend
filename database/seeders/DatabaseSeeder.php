<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);
        
        // Admin
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Tenant
        $tenant = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'tenant@example.com',
            'password' => bcrypt('password'),
            'role' => 'tenant',
        ]);

        // Landlord
        $landlord = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'landlord@example.com',
            'password' => bcrypt('password'),
            'role' => 'landlord',
        ]);

        // Maintenance Staff
        User::factory()->create([
            'name' => 'Maintenance Staff',
            'email' => 'maintenance@example.com',
            'password' => bcrypt('password'),
            'role' => 'maintenance_staff',
        ]);

        // Property
        $propertyId = \DB::table('properties')->insertGetId([
            'name' => 'Sunset Apartments',
            'address' => '123 Sunset Blvd',
            'type' => 'Residential',
            'units_count' => 10,
            'landlord_id' => $landlord->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Unit
        $unitId = \DB::table('units')->insertGetId([
            'property_id' => $propertyId,
            'unit_number' => '101',
            'floor' => 1,
            'type' => '2BHK',
            'status' => 'Occupied',
            'rent_amount' => 1200.00,
            'tenant_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lease
        \DB::table('leases')->insert([
            'unit_id' => $unitId,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subMonths(1),
            'end_date' => now()->addMonths(11),
            'rent_amount' => 1200.00,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Payment
        \DB::table('payments')->insert([
            'tenant_id' => $tenant->id,
            'unit_id' => $unitId,
            'lease_id' => 1, // Assuming first one
            'amount' => 1200.00,
            'payment_date' => now()->subDays(5),
            'type' => 'Rent',
            'method' => 'Bank Transfer',
            'status' => 'Paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
