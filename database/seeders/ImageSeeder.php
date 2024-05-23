<?php

namespace Database\Seeders;

use App\Models\Image;
use App\Models\Listing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $images = glob(database_path('seeders/images').'/*');

        //        foreach ($images as $image) {
        //            Storage::disk('public')->putFileAs(
        //                'images',
        //                $image,
        //                basename($image)
        //            );
        //        }

        $listings = Listing::all();

        foreach ($listings as $listing) {
            foreach ($images as $image) {
                Storage::disk('public')->putFileAs(
                    'listings/'.$listing->id.'/images',
                    $image,
                    basename($image)
                );

                $image = Image::factory()->make([
                    'filename' => basename($image),
                ]);

                $listing->images()->save($image);
            }
        }
    }
}
