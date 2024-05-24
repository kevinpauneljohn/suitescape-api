<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Listing;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $addons = [
            [
                'name' => 'Extra Bed',
                'description' => 'Extra bed for one person',
            ],
            [
                'name' => 'Extra Pillow',
                'description' => 'Extra pillow for one person',
            ],
        ];

        $listings = Listing::all();

        foreach ($listings as $listing) {
            foreach ($addons as $addon) {
                $addon = Addon::factory()->make($addon);

                $listing->addons()->save($addon);
            }
        }
    }
}
