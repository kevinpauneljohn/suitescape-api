<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdatePaymentStatusColumnInInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            DB::statement("ALTER TABLE `invoices` MODIFY `payment_status` ENUM('pending', 'paid', 'refunded', 'fully_refunded', 'partially_refunded') NOT NULL");
        });

        DB::table('invoices')
            ->where('payment_status', 'refunded')
            ->update(['payment_status' => 'fully_refunded']);

        Schema::table('invoices', function (Blueprint $table) {
            DB::statement("ALTER TABLE `invoices` MODIFY `payment_status` ENUM('pending', 'paid', 'fully_refunded', 'partially_refunded') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('invoices')
            ->where('payment_status', 'fully_refunded')
            ->update(['payment_status' => 'refunded']);

        Schema::table('invoices', function (Blueprint $table) {
            DB::statement("ALTER TABLE `invoices` MODIFY `payment_status` ENUM('pending', 'paid', 'refunded') NOT NULL");
        });
    }
}