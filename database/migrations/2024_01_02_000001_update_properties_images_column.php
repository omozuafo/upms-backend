<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite compatibility, we need to use a different approach
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support dropping columns easily, so we'll just add the new column
            Schema::table('properties', function (Blueprint $table) {
                $table->json('images')->nullable()->after('description');
            });
        } else {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('image_url');
                $table->json('images')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        } else {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('images');
                $table->string('image_url')->nullable();
            });
        }
    }
};

