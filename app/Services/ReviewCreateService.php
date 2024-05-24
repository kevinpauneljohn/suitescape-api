<?php

namespace App\Services;

use App\Models\Listing;

class ReviewCreateService
{
    public function createReview($listingId, $feedback, $overallRating, $serviceRatings)
    {
        $userId = auth()->id();
        $listing = Listing::findOrFail($listingId);

        $listing->reviews()->create([
            'user_id' => $userId,
            'content' => $feedback,
            'rating' => $overallRating,
        ]);

        $ratingsWithUserId = array_merge($serviceRatings, [
            'user_id' => $userId,
        ]);
        $listing->serviceRatings()->create($ratingsWithUserId);
    }
}
