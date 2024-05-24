<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PackageImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packageImages = glob(database_path('seeders/package-images').'/*');

        $packages = Package::all();

        foreach ($packages as $index => $package) {
            $assignedPackageImage = $packageImages[$index];

            Storage::disk('public')->putFileAs(
                'packages/'.$package->id.'/images',
                $assignedPackageImage,
                basename($assignedPackageImage)
            );

            $image = PackageImage::factory()->make([
                'filename' => basename($assignedPackageImage),
            ]);

            $package->packageImages()->save($image);
        }

    }
}
