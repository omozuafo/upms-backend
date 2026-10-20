<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function addIndexSafely($table, $columns, $name = null)
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                if ($name) {
                    $t->index($columns, $name);
                } else {
                    $t->index($columns);
                }
            });
        } catch (\Throwable $e) {
            // Ignore duplicate key/index errors
        }
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Properties
        $this->addIndexSafely('properties', 'landlord_id');
        $this->addIndexSafely('properties', 'status');
        $this->addIndexSafely('properties', ['status', 'landlord_id']);

        // Units
        $this->addIndexSafely('units', 'property_id');
        $this->addIndexSafely('units', 'tenant_id');
        $this->addIndexSafely('units', 'status');
        $this->addIndexSafely('units', ['property_id', 'status']);

        // Leases
        $this->addIndexSafely('leases', 'tenant_id');
        $this->addIndexSafely('leases', 'unit_id');
        $this->addIndexSafely('leases', 'status');
        $this->addIndexSafely('leases', ['status', 'end_date']);
        $this->addIndexSafely('leases', ['tenant_id', 'status']);

        // Payments
        $this->addIndexSafely('payments', 'tenant_id');
        $this->addIndexSafely('payments', 'lease_id');
        $this->addIndexSafely('payments', 'property_id');
        $this->addIndexSafely('payments', 'status');
        $this->addIndexSafely('payments', ['status', 'payment_date']);

        // Issues
        $this->addIndexSafely('issues', 'property_id');
        $this->addIndexSafely('issues', 'unit_id');
        $this->addIndexSafely('issues', 'reported_by');
        $this->addIndexSafely('issues', 'assigned_to');
        $this->addIndexSafely('issues', 'status');
        $this->addIndexSafely('issues', ['status', 'priority']);

        // Users
        $this->addIndexSafely('users', 'role');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
