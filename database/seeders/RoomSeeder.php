<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $listings = Listing::all();

        foreach ($listings as $listing) {

            foreach ($listing->roomCategories as $roomCategory) {
                //                if (rand(0, 1)) {
                //                    continue;
                //                }

                $room = Room::factory()->make([
                    'room_category_id' => $roomCategory->id,
                ]);

                $listing->rooms()->save($room);
            }
        }
    }
}
