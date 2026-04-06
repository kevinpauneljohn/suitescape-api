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
        Schema::create('rebook_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->references('id')->on('users')->cascadeOnDelete(); // guest
            $table->date('requested_date_start');
            $table->date('requested_date_end');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // Price snapshot at time of request
            $table->decimal('original_amount', 10, 2);
            $table->decimal('new_amount', 10, 2);
            $table->decimal('difference', 10, 2); // positive = extra charge, negative = refund
            $table->decimal('new_base_amount', 10, 2)->default(0);
            $table->decimal('new_guest_service_fee', 10, 2)->default(0);
            $table->decimal('new_vat', 10, 2)->default(0);
            $table->text('host_note')->nullable(); // optional note from host on rejection
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rebook_requests');
    }
};
