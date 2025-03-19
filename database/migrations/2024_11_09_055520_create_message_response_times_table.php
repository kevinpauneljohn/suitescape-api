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
        Schema::create('message_response_times', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id');
            $table->foreignUuid('message_id');
            $table->foreignUuid('user_id');
            $table->integer('response_time_seconds');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_response_times');
    }
};
