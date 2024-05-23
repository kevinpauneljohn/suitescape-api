<?php

namespace Database\Seeders;

use App\Models\Listing;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get number of videos and create that many listings
        $videos = glob(database_path('seeders/videos').'/*');
        $videoCount = count($videos);

        Listing::factory()->count($videoCount)->create();
        //        Listing::factory()->count(15)->create();
    }
}
