<?php

namespace Database\Seeders;

use App\Models\Image;
use App\Models\Listing;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $listings = Listing::all();

        foreach ($listings as $listing) {
            $images = Image::factory()->count(10)->make();

            foreach ($images as $index => $image) {
                $image['filename'] = 'ListingPhotoUnsplash' . $index + 1 . '.jpg';
                $listing->images()->save($image);
            }
        }
    }
}
