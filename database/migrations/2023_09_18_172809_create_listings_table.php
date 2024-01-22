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
            $table->timestamps();
        });

        // Add fulltext index
        DB::statement('ALTER TABLE listings ADD FULLTEXT fulltext_index (name, location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE listings DROP INDEX fulltext_index');
        Schema::dropIfExists('listings');
    }
};
