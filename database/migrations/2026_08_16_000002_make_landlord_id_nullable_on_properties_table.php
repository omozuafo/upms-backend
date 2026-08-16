<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE properties ALTER COLUMN landlord_id DROP NOT NULL;');
        } catch (\Exception $e) {
            // Fallback for SQLite or other drivers if DB::statement fails
            Schema::table('properties', function (Blueprint $table) {
                $table->unsignedBigInteger('landlord_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE properties ALTER COLUMN landlord_id SET NOT NULL;');
        } catch (\Exception $e) {
            Schema::table('properties', function (Blueprint $table) {
                $table->unsignedBigInteger('landlord_id')->nullable(false)->change();
            });
        }
    }
};
