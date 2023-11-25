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
            foreach ($listing->rooms as $room) {
                $reviews = Review::factory()->count(rand(0, 3))->make([
                    'room_id' => $room->id,
                ]);

                $listing->reviews()->saveMany($reviews);
            }
        }
    }
}
