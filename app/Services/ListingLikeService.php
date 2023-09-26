<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\User;

class ListingLikeService
{
    private Listing $listing;
    private string $userId;

    public function __construct(Listing $listing)
    {
        $this->listing = $listing;
        $this->userId = auth()->id();
    }

    public function addLike()
    {
        $this->listing->likes()->create([
            'user_id' => $this->userId,
        ]);
        $this->listing->increment('likes');
    }

    public function removeLike()
    {
        $this->listing->likes()->where('user_id', $this->userId)->delete();
        $this->listing->decrement('likes');
    }
}
