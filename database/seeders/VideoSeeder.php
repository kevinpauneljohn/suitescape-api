<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class VideoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $videos = glob(database_path('seeders/videos').'/*');

        $listings = Listing::all();

        foreach ($listings as $index => $listing) {
            $assignedVideo = $videos[$index];

            Storage::disk('public')->putFileAs(
                'listings/'.$listing->id.'/videos',
                $assignedVideo,
                basename($assignedVideo)
            );

            $video = Video::factory()->make([
                'filename' => basename($assignedVideo),
            ]);

            $listing->videos()->save($video);
        }
    }
}
