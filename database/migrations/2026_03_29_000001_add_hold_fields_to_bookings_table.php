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
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('hold_expires_at')->nullable()->after('status');
            $table->string('idempotency_key')->nullable()->unique()->after('hold_expires_at');
            $table->string('payment_intent_id')->nullable()->after('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['hold_expires_at', 'idempotency_key', 'payment_intent_id']);
        });
    }
};
