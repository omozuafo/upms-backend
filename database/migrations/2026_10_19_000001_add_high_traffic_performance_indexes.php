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
        // Notifications indexes (user_id and read/type polled continuously)
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['user_id', 'read']);
                $table->index(['user_id', 'type']);
            });
        }

        // Expenses indexes (filtered by status, property, unit, creator)
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->index('status');
                $table->index('property_id');
                $table->index('unit_id');
                $table->index('created_by');
                $table->index(['status', 'created_at']);
            });
        }

        // Wallet transactions indexes (user history ordered by date)
        if (Schema::hasTable('wallet_transactions')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->index(['user_id', 'created_at']);
            });
        }

        // User sessions composite index (session validation on every authenticated request)
        if (Schema::hasTable('user_sessions')) {
            Schema::table('user_sessions', function (Blueprint $table) {
                $table->index(['session_key', 'is_active']);
            });
        }

        // Payments additional composite indexes
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['tenant_id', 'status']);
                $table->index(['property_id', 'status']);
                $table->index(['type', 'status']);
            });
        }

        // Units tenant and status index
        if (Schema::hasTable('units')) {
            Schema::table('units', function (Blueprint $table) {
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'read']);
                $table->dropIndex(['user_id', 'type']);
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropIndex(['property_id']);
                $table->dropIndex(['unit_id']);
                $table->dropIndex(['created_by']);
                $table->dropIndex(['status', 'created_at']);
            });
        }

        if (Schema::hasTable('wallet_transactions')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'created_at']);
            });
        }

        if (Schema::hasTable('user_sessions')) {
            Schema::table('user_sessions', function (Blueprint $table) {
                $table->dropIndex(['session_key', 'is_active']);
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'status']);
                $table->dropIndex(['property_id', 'status']);
                $table->dropIndex(['type', 'status']);
            });
        }

        if (Schema::hasTable('units')) {
            Schema::table('units', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'status']);
            });
        }
    }
};
