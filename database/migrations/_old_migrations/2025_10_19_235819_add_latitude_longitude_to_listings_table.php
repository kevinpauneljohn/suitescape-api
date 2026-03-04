<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('latitude', 50)
                  ->nullable()
                  ->after('entire_place_weekend_price');

            $table->string('longitude', 50)
                  ->nullable()
                  ->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
