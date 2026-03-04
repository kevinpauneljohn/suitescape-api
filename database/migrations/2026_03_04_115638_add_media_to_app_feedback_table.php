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
        Schema::table('app_feedback', function (Blueprint $table) {
            $table->json('media')->nullable()->after('comment'); // Array of media file paths (images/videos)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_feedback', function (Blueprint $table) {
            $table->dropColumn('media');
        });
    }
};
