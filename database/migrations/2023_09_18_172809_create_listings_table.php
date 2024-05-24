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
        Schema::create('listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id');
            $table->string('name');
            $table->text('location');
            $table->text('description')->nullable();
            $table->enum('facility_type', ['house', 'hotel', 'apartment', 'condominium', 'cabin', 'villa']);
            $table->time('check_in_time');
            $table->time('check_out_time');
            $table->integer('adult_capacity');
            $table->integer('child_capacity');
            $table->boolean('is_pet_friendly')->default(false);
            $table->boolean('parking_lot')->default(false);
            $table->boolean('is_entire_place');
            $table->decimal('entire_place_price', 10, 2, true)->nullable();
            $table->fullText(['name', 'location']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
