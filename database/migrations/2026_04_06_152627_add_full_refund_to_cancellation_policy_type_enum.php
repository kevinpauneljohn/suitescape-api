<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand the cancellation_policy_type enum on the listings table to include 'full_refund'.
     * MySQL enum columns must be redeclared in full when altering.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE listings
            MODIFY COLUMN cancellation_policy_type
            ENUM('flexible','moderate','strict','super_strict_30','super_strict_60','long_term','full_refund')
            NOT NULL DEFAULT 'flexible'
        ");
    }

    public function down(): void
    {
        // Remove full_refund; reset any rows using it to 'flexible' first.
        DB::statement("
            UPDATE listings SET cancellation_policy_type = 'flexible'
            WHERE cancellation_policy_type = 'full_refund'
        ");

        DB::statement("
            ALTER TABLE listings
            MODIFY COLUMN cancellation_policy_type
            ENUM('flexible','moderate','strict','super_strict_30','super_strict_60','long_term')
            NOT NULL DEFAULT 'flexible'
        ");
    }
};
