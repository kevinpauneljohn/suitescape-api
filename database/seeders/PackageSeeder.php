<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get number of images and create that many packages
        $packageImages = glob(database_path('seeders/package-images').'/*');
        $imageCount = count($packageImages);

        $packageNames = [
            'Baguio Blissful Retreat Package',
            'Bohol Island Escape Package',
            'Ilocos Cultural Discovery Package',
            'Puerto Galera Serenity Package',
            'Sagada and Baguio Scenic Journey Package',
        ];

        // Create packages with names
        for ($i = 0; $i < $imageCount; $i++) {
            Package::factory()->create([
                'name' => $packageNames[$i],
            ]);
        }

        //        Package::factory()->count(5)->create();
    }
}
