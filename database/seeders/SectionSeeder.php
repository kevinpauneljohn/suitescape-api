<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Log;
use ProtoneMedia\LaravelFFMpeg\Drivers\UnknownDurationException;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $videos = Video::all();

        foreach ($videos as $video) {
            // Get the duration of the video
            try {
                $mediaDuration = FFMpeg::fromDisk('public')
                    ->open('listings/'.$video->listing_id.'/videos/'.$video->filename)
                    ->getDurationInMiliseconds();
            } catch (UnknownDurationException) {
                Log::error('Could not get duration for video '.$video->filename);

                $mediaDuration = 10000;
            }

            $sections = Section::factory()->count(5)->sequence(function () use ($mediaDuration) {
                // Generate random milliseconds based on the video duration
                $milliseconds = fake()->numberBetween(1000, $mediaDuration);

                // Ensure milliseconds is unique
                while (Section::where('milliseconds', $milliseconds)->exists()) {
                    $milliseconds = fake()->numberBetween(1000, $mediaDuration);
                }

                return [
                    'milliseconds' => $milliseconds,
                ];
            })->make();

            $video->sections()->saveMany($sections);
        }
    }
}
