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
            // Tracks the PayMongo source ID for GCash/GrabPay rebook difference payments
            $table->string('epayment_source_id')->nullable()->after('host_note');
            // 'pending' | 'paid' — only relevant when difference > 0
            $table->string('payment_status')->nullable()->after('epayment_source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rebook_requests', function (Blueprint $table) {
            $table->dropColumn(['epayment_source_id', 'payment_status']);
        });
    }
};
