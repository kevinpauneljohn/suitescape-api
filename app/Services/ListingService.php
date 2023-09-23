<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ListingService
{
    public function createListingAndRoomCategory(User $user): Listing
    {
        // Using DB, if an error occurs while creating either the listing or room category, no changes will be committed to the database.
        return DB::transaction(function () use ($user) {
            // Temporary solution for now so that video can be uploaded
            $listing = $user->listings()->save(
                Listing::factory()->make()
            );

            $listing->roomCategories()->save(
                RoomCategory::factory()->make()
            );

            return $listing;
        });
    }
}
