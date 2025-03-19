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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->enum('role', ['property_owner', 'property_manager', 'hosting_service_provider', 'other']);
            $table->string('bank_name')->nullable();
            $table->enum('bank_type', ['personal', 'joint', 'business'])->nullable();
            $table->string('swift_code')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('email');
            $table->string('phone_number');
            $table->date('dob');
            $table->string('pob');
            $table->string('citizenship');
            $table->string('billing_country');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
