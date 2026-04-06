<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add guest_service_fee column to bookings table
        Schema::table('bookings', function (Blueprint $table) {
            // Guest service fee amount charged to guest on top of subtotal
            $table->decimal('guest_service_fee', 10, 2)->default(0)->after('base_amount');
        });

        // Add guest_service_fee_percentage constant (default 15%)
        DB::table('constants')->insertOrIgnore([
            'key' => 'guest_service_fee_percentage',
            'value' => '15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Convert host suitescape_fee from flat amount to percentage (default 3%)
        // The old value was a flat amount, now it represents a percentage
        DB::table('constants')
            ->where('key', 'suitescape_fee')
            ->update(['value' => '3']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('guest_service_fee');
        });

        DB::table('constants')
            ->where('key', 'guest_service_fee_percentage')
            ->delete();

        // Revert suitescape_fee back to flat amount
        DB::table('constants')
            ->where('key', 'suitescape_fee')
            ->update(['value' => '100']);
    }
};
