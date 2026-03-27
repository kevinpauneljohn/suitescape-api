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
        Schema::table('bookings', function (Blueprint $table) {
            // Suitescape platform fee charged to host for each completed booking
            $table->decimal('suitescape_fee', 10, 2)->default(0)->after('base_amount');
            // Net earnings for host after fee deduction (amount - suitescape_fee)
            $table->decimal('host_earnings', 10, 2)->default(0)->after('suitescape_fee');
        });

        // Update existing completed bookings to set host_earnings = amount (no fee applied retroactively)
        DB::table('bookings')
            ->where('status', 'completed')
            ->update([
                'host_earnings' => DB::raw('amount'),
                'suitescape_fee' => 0,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['suitescape_fee', 'host_earnings']);
        });
    }
};
