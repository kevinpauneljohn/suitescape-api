<?php

namespace Database\Seeders;

use App\Models\Constant;
use Illuminate\Database\Seeder;

class ConstantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Constant::create([
            'key' => 'cancellation_policy',
            'value' => 'Free cancellation after 2 days of booking',
        ]);

        Constant::create([
            'key' => 'cancellation_fee',
            'value' => 100,
        ]);

        Constant::create([
            'key' => 'free_cancellation_days',
            'value' => 3,
        ]);

        Constant::create([
            'key' => 'suitescape_fee',
            'value' => 3, // 3% host platform fee (percentage)
        ]);

        Constant::create([
            'key' => 'guest_service_fee_percentage',
            'value' => 15, // 15% guest service fee (percentage)
        ]);

        Constant::create([
            'key' => 'vat_percentage',
            'value' => 12, // 12% VAT (Philippines standard rate)
        ]);
    }
}
