<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\ListingNearbyPlace;
use App\Models\NearbyPlace;
use Illuminate\Database\Seeder;

class ListingNearbyPlaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $listings = Listing::all();
        $nearbyPlaces = NearbyPlace::all();

        foreach ($listings as $listing) {
            foreach ($nearbyPlaces as $nearbyPlace) {
                if (rand(0, 1)) {
                    continue;
                }

                $listingNearbyPlace = ListingNearbyPlace::factory()->make([
                    'nearby_place_id' => $nearbyPlace->id,
                ]);

                $listing->listingNearbyPlaces()->save($listingNearbyPlace);
            }
        }
    }
}
