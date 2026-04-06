<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand the cancellation_policy_type enum to include 'no_cancellation'.
     * MySQL enums must be redeclared in full.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE listings
            MODIFY COLUMN cancellation_policy_type
            ENUM('flexible','moderate','strict','super_strict_30','super_strict_60','long_term','full_refund','no_cancellation')
            NOT NULL DEFAULT 'flexible'
        ");
    }

    public function down(): void
    {
        // Reset any rows using no_cancellation back to flexible before shrinking enum.
        DB::statement("
            UPDATE listings SET cancellation_policy_type = 'flexible'
            WHERE cancellation_policy_type = 'no_cancellation'
        ");

        DB::statement("
            ALTER TABLE listings
            MODIFY COLUMN cancellation_policy_type
            ENUM('flexible','moderate','strict','super_strict_30','super_strict_60','long_term','full_refund')
            NOT NULL DEFAULT 'flexible'
        ");
    }
};
