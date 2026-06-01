<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('type'); // Residential, Commercial
            $table->string('status')->default('Active');
            $table->integer('units_count')->default(0);
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade'); // Assumes users table exists
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->string('unit_number');
            $table->integer('floor')->nullable();
            $table->string('type'); // 1BHK, etc.
            $table->string('status')->default('Vacant');
            $table->decimal('rent_amount', 10, 2);
            $table->text('description')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('rent_amount', 10, 2);
            $table->decimal('security_deposit', 10, 2)->nullable();
            $table->string('status')->default('Active');
            $table->text('terms')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lease_id')->nullable()->constrained('leases')->onDelete('cascade');
            $table->foreignId('property_id')->nullable()->constrained('properties')->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('cascade');
            $table->string('type'); // Rent, Service Charge
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->string('status')->default('Paid');
            $table->text('description')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('cascade');
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->string('priority')->default('Low');
            $table->string('status')->default('Open');
            $table->json('images')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->text('description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('Pending');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('leases');
        Schema::dropIfExists('units');
        Schema::dropIfExists('properties');
    }
};
