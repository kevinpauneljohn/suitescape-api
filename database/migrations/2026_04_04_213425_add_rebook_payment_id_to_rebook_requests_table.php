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
            // PayMongo payment ID returned after successfully charging the rebook difference.
            // Stored so the full amount (original + rebook) can be refunded on cancellation.
            $table->string('rebook_payment_id')->nullable()->after('epayment_source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rebook_requests', function (Blueprint $table) {
            $table->dropColumn('rebook_payment_id');
        });
    }
};
