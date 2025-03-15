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
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id');
            $table->foreignUuid('sender_id')->constrained('users');
            $table->foreignUuid('receiver_id')->constrained('users');
            $table->foreignUuid('listing_id')->nullable();
            $table->text('content');
            $table->dateTime('read_at')->nullable();
            $table->fullText(['content']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
