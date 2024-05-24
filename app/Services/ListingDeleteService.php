<?php

namespace App\Services;

use App\Models\Listing;
use Exception;

class ListingDeleteService
{
    /**
     * @throws Exception
     */
    public function deleteListing($listingId)
    {
        $listing = Listing::findOrFail($listingId);

        // Check if the listing has any bookings
        if ($listing->bookings()->exists()) {
            // If there are bookings, throw an exception
            throw new Exception('Cannot delete listing with active bookings.');
        }

        $listing->delete();

        return $listing;
    }
}
