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
        Schema::create('guest_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('(UUID())'));
            // The booking this review is for
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            // The guest being reviewed
            $table->foreignUuid('guest_id')->constrained('users')->cascadeOnDelete();
            // The host who wrote the review
            $table->foreignUuid('host_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating');       // 1–5
            $table->text('content')->nullable(); // written review
            $table->timestamps();

            // One host review per guest per booking
            $table->unique(['booking_id', 'host_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_reviews');
    }
};
