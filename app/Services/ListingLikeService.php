<?php

namespace App\Services;

use App\Models\Listing;

class ListingLikeService
{
    private Listing $listing;

    private string $userId;

    public function __construct(Listing $listing)
    {
        $this->listing = $listing;
        $this->userId = auth()->id();
    }

    public function addLike(): void
    {
        $this->listing->likes()->create([
            'user_id' => $this->userId,
        ]);
    }

    public function removeLike(): void
    {
        $this->listing->likes()->where('user_id', $this->userId)->delete();
    }
}
