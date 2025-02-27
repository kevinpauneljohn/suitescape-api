<?php

namespace Database\Seeders;

use App\Models\Listing;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get number of videos and create that many listings
        $videos = glob(database_path('seeders/videos').'/*');

        $listings = [
            [
                'name' => 'Sunset View Beach Resort',
                'location' => 'Calatagan, Batangas, Philippines',
                'description' => 'A beautiful beach resort with stunning sunset views and luxurious amenities.',
            ],
            [
                'name' => 'Manila Bay Apartments',
                'location' => 'Roxas Blvd, Malate, Manila, 1004 Metro Manila, Philippines',
                'description' => 'Modern apartments with a picturesque view of Manila Bay, close to shopping and dining.',
            ],
            [
                'name' => 'Tagaytay Highlands Villa',
                'location' => 'Tagaytay Highlands, Brgy. Calabuso, Tagaytay City, Cavite, Philippines',
                'description' => 'A serene villa located in the highlands of Tagaytay, offering cool climate and scenic views.',
            ],
            [
                'name' => 'Cebu City Condo',
                'location' => 'Cebu Business Park, Cebu City, Cebu, Philippines',
                'description' => 'Contemporary condo units in the heart of Cebu City, perfect for urban living.',
            ],
            [
                'name' => 'Boracay Island Retreat',
                'location' => 'Station 1, Balabag, Boracay Island, Malay, Aklan, Philippines',
                'description' => 'A relaxing retreat on the famous white sand beaches of Boracay Island.',
            ],
            [
                'name' => 'Palawan Paradise Resort',
                'location' => 'El Nido, Palawan, Philippines',
                'description' => 'A tropical paradise resort located in the pristine islands of Palawan.',
            ],
            [
                'name' => 'Baguio Mountain Cabin',
                'location' => 'Camp John Hay, Baguio City, Benguet, Philippines',
                'description' => 'A cozy mountain cabin in Baguio, perfect for a getaway in the cool highlands.',
            ],
            [
                'name' => 'Davao City Bungalow',
                'location' => 'J.P. Laurel Ave, Davao City, 8000 Davao del Sur, Philippines',
                'description' => 'A spacious bungalow in Davao City, surrounded by lush greenery and near local attractions.',
            ],
            [
                'name' => 'Subic Bay Waterfront House',
                'location' => 'AYC Compound, National Hwy, Subic, 2209 Zambales, Philippines',
                'description' => 'A waterfront house in Subic Bay, offering beautiful sea views and water activities.',
            ],
            [
                'name' => 'Batangas Beach House',
                'location' => 'Laiya, San Juan, Batangas, Philippines',
                'description' => 'A charming beach house in Batangas, ideal for a relaxing seaside vacation.',
            ],
            [
                'name' => 'Siargao Surf Lodge',
                'location' => 'General Luna, Siargao Island, Surigao del Norte, Philippines',
                'description' => 'A surfer\'s paradise lodge steps away from the famous Cloud 9 surf spot.',
            ],
            [
                'name' => 'Vigan Heritage House',
                'location' => 'Calle Crisologo, Vigan City, Ilocos Sur, Philippines',
                'description' => 'A restored colonial mansion in the historic district of Vigan, offering a glimpse into Philippine history.',
            ],
            [
                'name' => 'Coron Island Villa',
                'location' => 'Coron, Palawan, Philippines',
                'description' => 'A luxurious villa overlooking the pristine waters of Coron, perfect for island hopping adventures.',
            ],
            [
                'name' => 'Clark Pampanga Resort',
                'location' => 'Clark Freeport Zone, Pampanga, Philippines',
                'description' => 'A modern resort near Clark International Airport, featuring golf courses and entertainment facilities.',
            ],
            [
                'name' => 'Camiguin Island Retreat',
                'location' => 'Mambajao, Camiguin, Philippines',
                'description' => 'A peaceful retreat on the volcanic island of Camiguin, surrounded by hot springs and waterfalls.',
            ],
        ];

        // Create listings based on the number of videos
        for ($i = 0; $i < count($videos); $i++) {
            $listing = $listings[$i % count($listings)];

            Listing::factory()->create([
                'name' => $listing['name'],
                'location' => $listing['location'],
                'description' => $listing['description'],
            ]);
        }

        //        Listing::factory()->count($videoCount)->create();
        //        Listing::factory()->count(15)->create();
    }
}
