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
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id');
            $table->foreignUuid('user_id');
            $table->string('listing_name');
            $table->string('room_category_name');
            $table->date('date_start');
            $table->date('date_end');
            $table->foreignUuid('coupon_id');
            $table->decimal('coupon_discount_amount');
            $table->decimal('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
