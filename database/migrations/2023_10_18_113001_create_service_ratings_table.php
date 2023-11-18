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
        Schema::create('service_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('listing_id');
            $table->integer('cleanliness');
            $table->integer('price_affordability');
            $table->integer('facility_service');
            $table->integer('comfortability');
            $table->integer('staff');
            $table->integer('location');
            $table->integer('privacy_and_security');
            $table->integer('accessibility');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_ratings');
    }
};
