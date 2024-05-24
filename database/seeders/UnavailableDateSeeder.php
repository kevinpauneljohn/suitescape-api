<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\Room;
use App\Models\UnavailableDate;
use Illuminate\Database\Seeder;

class UnavailableDateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // For multiple rooms
        $rooms = Room::all();

        foreach ($rooms as $room) {
            $roomUnavailableDates = UnavailableDate::factory(rand(5, 10))->make();

            $room->unavailableDates()->saveMany($roomUnavailableDates);
        }

        // For entire place
        $listings = Listing::all();

        foreach ($listings as $listing) {
            $listingUnavailableDates = UnavailableDate::factory(rand(5, 10))->make();

            $listing->unavailableDates()->saveMany($listingUnavailableDates);
        }
    }
}
