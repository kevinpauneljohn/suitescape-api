<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $amenities = [
            'wifi',
            'air_conditioning',
            'karaoke',
            'swimming_pool',
            'toiletries',
        ];

        foreach ($amenities as $amenity) {
            Amenity::factory()->create([
                'name' => $amenity,
            ]);
        }
    }
}
