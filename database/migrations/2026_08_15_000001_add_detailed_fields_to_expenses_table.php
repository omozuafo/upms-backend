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
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->after('invoice_number');
            }
            if (!Schema::hasColumn('expenses', 'purpose')) {
                $table->string('purpose')->nullable()->after('category');
            }
            if (!Schema::hasColumn('expenses', 'account_name')) {
                $table->string('account_name')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('expenses', 'account_number')) {
                $table->string('account_number')->nullable()->after('account_name');
            }
            if (!Schema::hasColumn('expenses', 'payment_timestamp')) {
                $table->timestamp('payment_timestamp')->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('expenses', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('expenses', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'receipt_number',
                'purpose',
                'account_name',
                'account_number',
                'payment_timestamp',
                'created_by',
                'rejection_reason',
            ]);
        });
    }
};
