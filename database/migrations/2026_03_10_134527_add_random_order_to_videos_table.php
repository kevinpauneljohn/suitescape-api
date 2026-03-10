<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // Add random_order column for deterministic random ordering with cursor pagination
            $table->unsignedBigInteger('random_order')->default(0)->after('is_approved');
            $table->index('random_order');
        });

        // Initialize with random values
        DB::statement('UPDATE videos SET random_order = FLOOR(RAND() * 1000000000)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['random_order']);
            $table->dropColumn('random_order');
        });
    }
};
