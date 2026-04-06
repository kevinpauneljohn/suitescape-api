<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // VAT amount charged to the guest (computed from subtotal * vat_percentage)
            $table->decimal('vat', 10, 2)->default(0)->after('guest_service_fee');
        });

        // Add vat_percentage constant (default 12% for Philippines)
        DB::table('constants')->updateOrInsert(
            ['key' => 'vat_percentage'],
            ['key' => 'vat_percentage', 'value' => '12', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('vat');
        });

        DB::table('constants')->where('key', 'vat_percentage')->delete();
    }
};
