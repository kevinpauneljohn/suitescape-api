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
        Schema::create('payout_method_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payout_method_id');
            $table->string('type');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->enum('role', ['property_owner', 'property_manager', 'hosting_service_provider', 'other']);
            $table->string('bank_name');
            $table->enum('bank_type', ['personal', 'joint', 'business']);
            $table->string('swift_code');
            $table->string('bank_code');
            $table->string('email');
            $table->string('phone');
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
        Schema::dropIfExists('payout_method_details');
    }
};
