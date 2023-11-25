<?php

namespace Database\Seeders;

use App\Models\BookingPolicy;
use App\Models\Listing;
use Illuminate\Database\Seeder;

class BookingPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bookingPolicies = [
            'Non-refundable',
            'Payment first',
        ];

        $listings = Listing::all();

        foreach ($listings as $listing) {
            foreach ($bookingPolicies as $bookingPolicy) {
                if (rand(0, 1)) {
                    continue;
                }

                $bookingPolicy = BookingPolicy::factory()->make([
                    'name' => $bookingPolicy,
                ]);

                $listing->bookingPolicies()->save($bookingPolicy);
            }
        }
    }
}
