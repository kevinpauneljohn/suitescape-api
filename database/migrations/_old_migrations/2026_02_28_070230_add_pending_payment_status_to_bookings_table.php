<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds 'pending_payment' status for bookings awaiting GCash/GrabPay payment confirmation.
     * This status indicates the booking has been created but payment hasn't been confirmed by webhook yet.
     */
    public function up(): void
    {
        // Modify the enum to include pending_payment
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('to_pay', 'pending_payment', 'upcoming', 'ongoing', 'cancelled', 'completed', 'to_rate') DEFAULT 'to_pay'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any pending_payment bookings to to_pay to avoid data loss
        DB::statement("UPDATE bookings SET status = 'to_pay' WHERE status = 'pending_payment'");
        
        // Remove pending_payment from the enum
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('to_pay', 'upcoming', 'ongoing', 'cancelled', 'completed', 'to_rate') DEFAULT 'to_pay'");
    }
};
