<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        $this->call([
            SettingSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            ListingSeeder::class,
            BookingPolicySeeder::class,
            NearbyPlaceSeeder::class,
            ServiceRatingSeeder::class,
            RoomCategorySeeder::class,
            RoomSeeder::class,
            RoomRuleSeeder::class,
            ReviewSeeder::class,
            AmenitySeeder::class,
            RoomAmenitySeeder::class,
            VideoSeeder::class,
            ImageSeeder::class,
        ]);
    }
}
