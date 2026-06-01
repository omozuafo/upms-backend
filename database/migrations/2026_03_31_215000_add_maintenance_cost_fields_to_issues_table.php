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
        Schema::table('issues', function (Blueprint $table) {
            $table->decimal('budget_cost', 10, 2)->nullable()->after('status');
            $table->text('maintenance_report')->nullable()->after('budget_cost');
            $table->string('account_review_status')->nullable()->after('maintenance_report'); // 'Pending', 'Accepted', 'Rejected', 'Disputed'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['budget_cost', 'maintenance_report', 'account_review_status']);
        });
    }
};
