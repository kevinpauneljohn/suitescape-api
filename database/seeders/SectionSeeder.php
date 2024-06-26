<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Owenoj\LaravelGetId3\GetId3;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws \getid3_exception
     */
    public function run(): void
    {
        $labels = [
            'Living Room',
            'Dining Room',
            'Bathroom',
            'Bedroom',
            'Kitchen',
            'Garage',
            'Backyard',
            'Front Yard',
        ];

        $videos = Video::all();

        foreach ($videos as $video) {
            // Get the duration of the video in milliseconds
            //            try {
            //                $mediaDuration = FFMpeg::fromDisk('public')
            //                    ->open('listings/'.$video->listing_id.'/videos/'.$video->filename)
            //                    ->getDurationInMiliseconds();
            //            } catch (UnknownDurationException) {
            //                Log::error('Could not get duration for video '.$video->filename);
            //
            //                $mediaDuration = 10000;
            //            }
            $mediaDuration = GetId3::fromDiskAndPath('public', 'listings/'.$video->listing_id.'/videos/'.$video->filename)->getPlaytimeSeconds() * 1000;

            $sections = Section::factory()->count(5)->sequence(function ($sequence) use ($mediaDuration, $labels) {
                // Generate random milliseconds based on the video duration
                $milliseconds = fake()->numberBetween(1000, $mediaDuration);

                // Ensure milliseconds is unique
                while (Section::where('milliseconds', $milliseconds)->exists()) {
                    $milliseconds = fake()->numberBetween(1000, $mediaDuration);
                }

                $label = $labels[$sequence->index] ?? null;

                return array_filter([
                    'label' => $label,
                    'milliseconds' => $milliseconds,
                ]);
            })->make();

            $video->sections()->saveMany($sections);
        }
    }
}
