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
        // Extend status enum to include 'expired'
        DB::statement("ALTER TABLE rebook_requests MODIFY COLUMN status ENUM('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending'");

        Schema::table('rebook_requests', function (Blueprint $table) {
            // Pending requests expire 12 hours after submission if the host hasn't responded
            $table->timestamp('expires_at')->nullable()->after('responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rebook_requests', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        // Revert status enum
        DB::statement("ALTER TABLE rebook_requests MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
