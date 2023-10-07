<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\RoomCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoomCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $listings = Listing::all();

        foreach ($listings as $listing) {
            $listing->roomCategories()->saveMany(
                RoomCategory::factory()->count(rand(1, 3))->make()
            );
        }
    }
}
