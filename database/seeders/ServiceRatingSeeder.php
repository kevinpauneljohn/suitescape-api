<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\ServiceRating;
use Illuminate\Database\Seeder;

class ServiceRatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $listings = Listing::all();

        foreach ($listings as $listing) {
            $serviceRatings = ServiceRating::factory()->count(10)->make();

            $listing->serviceRatings()->saveMany($serviceRatings);
        }
    }
}
