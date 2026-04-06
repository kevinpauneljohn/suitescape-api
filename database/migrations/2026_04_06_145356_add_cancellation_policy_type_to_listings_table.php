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
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('cancellation_policy_type', [
                'flexible',
                'moderate',
                'strict',
                'super_strict_30',
                'super_strict_60',
                'long_term',
            ])->default('flexible')->after('is_entire_place');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('cancellation_policy_type');
        });
    }
};
