<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $listings = Listing::all();

        foreach ($listings as $listing) {
            $reviews = Review::factory()->count(rand(0, 10))->make();

            $listing->reviews()->saveMany($reviews);
        }
    }
}
