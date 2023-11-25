<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomAmenity;
use Illuminate\Database\Seeder;

class RoomAmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = Room::all();
        $amenities = Amenity::all();

        foreach ($rooms as $room) {
            foreach ($amenities as $amenity) {
                if (rand(0, 1)) {
                    continue;
                }

                $roomAmenity = RoomAmenity::factory()->make([
                    'amenity_id' => $amenity->id,
                ]);

                $room->roomAmenities()->save($roomAmenity);
            }
        }
    }
}
