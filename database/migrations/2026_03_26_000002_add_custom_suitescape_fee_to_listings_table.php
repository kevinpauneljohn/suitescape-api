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
            // Custom Suitescape fee for this listing (null = use global default)
            // This allows setting different fees for affiliates/partners
            $table->decimal('custom_suitescape_fee', 10, 2)->nullable()->after('longitude');
            
            // Flag to indicate if this is a partner/affiliate listing
            $table->boolean('is_partner')->default(false)->after('custom_suitescape_fee');
            
            // Partner notes (for admin reference)
            $table->string('partner_notes')->nullable()->after('is_partner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['custom_suitescape_fee', 'is_partner', 'partner_notes']);
        });
    }
};
