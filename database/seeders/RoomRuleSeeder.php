<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomRule;
use Illuminate\Database\Seeder;

class RoomRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = Room::all();

        foreach ($rooms as $room) {
            $roomRule = RoomRule::factory()->make();

            $room->roomRule()->save($roomRule);
        }
    }
}
