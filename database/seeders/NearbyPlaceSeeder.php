<?php

namespace Database\Seeders;

use App\Models\Listing;
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
            'restaurants',
            'malls',
            'bank',
            'church',
            'supermarket',
            'hospital',
        ];

        $listings = Listing::all();

        foreach ($listings as $listing) {
            foreach ($nearbyPlaces as $nearbyPlace) {
                if (rand(0, 1)) {
                    continue;
                }

                $nearbyPlace = NearbyPlace::factory()->make([
                    'name' => $nearbyPlace,
                ]);

                $listing->nearbyPlaces()->save($nearbyPlace);
            }
        }
    }
}
