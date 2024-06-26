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
        Schema::create('room_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('listing_id');
            $table->string('name', 100);
            $table->string('description', 5000)->nullable();
            $table->integer('floor_area');
            $table->json('type_of_beds');
            $table->integer('pax');
            $table->decimal('weekday_price', 10, 2, true);
            $table->decimal('weekend_price', 10, 2, true);
            $table->integer('quantity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_categories');
    }
};
