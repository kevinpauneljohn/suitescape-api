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
            $table->string('name');
            $table->integer('size');
            $table->json('type_of_beds');
            $table->integer('pax');
            $table->decimal('price');
            $table->decimal('tax');
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
