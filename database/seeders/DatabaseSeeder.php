<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $publicFolders = [
            'avatars',
            'listings',
            'packages',
        ];

        // Clear out the public folders
        foreach ($publicFolders as $folder) {
            File::deleteDirectory(storage_path('app/public/'.$folder));
        }

        $this->call([
            ConstantSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            ListingSeeder::class,
            BookingPolicySeeder::class,
            NearbyPlaceSeeder::class,
            ListingNearbyPlaceSeeder::class,
            ServiceRatingSeeder::class,
            AddonSeeder::class,
            RoomCategorySeeder::class,
            RoomSeeder::class,
            RoomRuleSeeder::class,
            UnavailableDateSeeder::class,
            ReviewSeeder::class,
            AmenitySeeder::class,
            RoomAmenitySeeder::class,
            PackageSeeder::class,
            PackageImageSeeder::class,
            VideoSeeder::class,
            SectionSeeder::class,
            ImageSeeder::class,
        ]);
    }
}
