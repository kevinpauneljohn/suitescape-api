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
        Schema::create('special_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('listing_id')->nullable();
            $table->foreignUuid('room_id')->nullable();
            $table->string('title');
            $table->decimal('price', 10, 2, true);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_rates');
    }
};
