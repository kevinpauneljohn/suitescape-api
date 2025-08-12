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
        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('booking_id', 36);
            $table->char('user_id', 36)->nullable();
            $table->string('payment_id', 100)->nullable();
            $table->string('refund_id', 100)->nullable();
            $table->string('status')->default('pending');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('PHP');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_cancellations');
    }
};
