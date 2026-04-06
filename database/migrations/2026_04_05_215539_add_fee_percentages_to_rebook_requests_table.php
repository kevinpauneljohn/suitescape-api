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
        Schema::table('rebook_requests', function (Blueprint $table) {
            $table->decimal('guest_service_fee_percentage', 5, 2)->default(15)->after('new_guest_service_fee');
            $table->decimal('vat_percentage', 5, 2)->default(12)->after('new_vat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rebook_requests', function (Blueprint $table) {
            $table->dropColumn(['guest_service_fee_percentage', 'vat_percentage']);
        });
    }
};
