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
        Schema::table('reviews', function (Blueprint $table) {
            // Link review to the specific booking so we can mark booking as reviewed
            $table->foreignUuid('booking_id')->nullable()->constrained('bookings')->nullOnDelete()->after('user_id');
            // Timestamp when the review was submitted (for auto-expiry logic)
            $table->timestamp('reviewed_at')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn(['booking_id', 'reviewed_at']);
        });
    }
};
