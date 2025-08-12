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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('hook_id')->unique();
            $table->string('type')->nullable();
            $table->longText('disabled_reason')->nullable();
            $table->json('events')->nullable();
            $table->boolean('livemode')->default(false);
            $table->string('status')->nullable();
            $table->string('url')->nullable();
            $table->string('secret_key')->nullable();
            $table->timestamp('paymongo_created_at')->nullable();
            $table->timestamp('paymongo_updated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
