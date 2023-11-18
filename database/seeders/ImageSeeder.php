<?php

namespace Database\Seeders;

use App\Models\Image;
use App\Models\Listing;
use Illuminate\Database\Seeder;

class ImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $listings = Listing::all();

        $filename = 'ListingPhotoUnsplash';

        foreach ($listings as $listing) {

            for ($i = 1; $i <= 10; $i++) {
                $image = Image::factory()->make([
                    'filename' => "$filename$i.jpg",
                ]);

                $listing->images()->save($image);
            }
        }
    }
}
