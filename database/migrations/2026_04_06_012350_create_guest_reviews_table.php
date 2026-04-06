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
        // Duplicate migration — guest_reviews table was already created in
        // 2026_04_06_012228_create_guest_reviews_table. This is a no-op.
        if (Schema::hasTable('guest_reviews')) {
            return;
        }

        Schema::create('guest_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('(UUID())'));
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('guest_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('content')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'host_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank — owned by 2026_04_06_012228
    }
};
