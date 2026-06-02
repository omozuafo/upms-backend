<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for better query performance
        
        // Properties table indexes
        Schema::table('properties', function (Blueprint $table) {
            $table->index('landlord_id');
            $table->index('status');
            $table->index(['status', 'landlord_id']);
        });

        // Units table indexes
        Schema::table('units', function (Blueprint $table) {
            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('status');
            $table->index(['property_id', 'status']);
        });

        // Leases table indexes
        Schema::table('leases', function (Blueprint $table) {
            $table->index('tenant_id');
            $table->index('unit_id');
            // Note: leases table does NOT have a property_id column
            $table->index('status');
            $table->index(['status', 'end_date']);
            $table->index(['tenant_id', 'status']);
        });

        // Payments table indexes
        Schema::table('payments', function (Blueprint $table) {
            $table->index('tenant_id');
            $table->index('lease_id');
            $table->index('property_id');
            $table->index('status');
            $table->index(['status', 'payment_date']);
        });

        // Issues table indexes
        Schema::table('issues', function (Blueprint $table) {
            $table->index('property_id');
            $table->index('unit_id');
            $table->index('reported_by');
            $table->index('assigned_to');
            $table->index('status');
            $table->index(['status', 'priority']);
        });

        // Users table indexes
        // Note: email already has a unique index from the create_users_table migration
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['landlord_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'landlord_id']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['property_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['property_id', 'status']);
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['unit_id']);
            // Note: no property_id index to drop
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'end_date']);
            $table->dropIndex(['tenant_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['lease_id']);
            $table->dropIndex(['property_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'payment_date']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['property_id']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['reported_by']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'priority']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            // Note: email unique index is managed by create_users_table migration
        });
    }
};
