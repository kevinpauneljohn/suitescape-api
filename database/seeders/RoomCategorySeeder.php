<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\RoomCategory;
use Illuminate\Database\Seeder;

class RoomCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ['Single', 'Double', 'Triple', 'Queen', 'King', 'Twin', 'Hollywood Twin Room', 'Double-double', 'Studio', 'Suite', 'Mini Suite', 'President Suite', 'Apartments', 'Connecting Rooms', 'Murphy Room', 'Accessible Room', 'Cabana', 'Adjoining Rooms', 'Adjacent Rooms', 'Villa', 'Executive Floor', 'Smoking Room'];

        $listings = Listing::hasMultipleRooms()->get();

        foreach ($listings as $listing) {
            foreach ($categories as $category) {
                if (rand(0, 1)) {
                    continue;
                }

                $roomCategory = RoomCategory::factory()->make([
                    'name' => $category,
                ]);

                $listing->roomCategories()->save($roomCategory);
            }
        }
    }
}
