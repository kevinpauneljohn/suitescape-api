<?php

namespace Database\Seeders;

use App\Models\NearbyPlace;
use Illuminate\Database\Seeder;

class NearbyPlaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nearbyPlaces = [
            'restaurant',
            'mall',
            'hospital',
            'bank',
            'church',
            'supermarket',
        ];

        foreach ($nearbyPlaces as $place) {
            NearbyPlace::factory()->create([
                'name' => $place,
            ]);
        }
    }
}
