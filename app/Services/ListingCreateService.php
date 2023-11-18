<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\User;

class ListingCreateService
{
    private User $user;

    private Listing $listing;

    private array $videoData;

    public function __construct(string $listingId, string $filename, array $videoData)
    {
        $videoData['filename'] = $filename;

        $this->videoData = $videoData;
        $this->user = auth()->user();
        $this->listing = Listing::find($listingId);
    }

    public function createListingVideo()
    {
        return $this->listing->videos()->create([
            'user_id' => $this->user->id,
            'filename' => $this->videoData['filename'],
            'privacy' => $this->videoData['privacy'],
        ]);
    }

    public function createListingImage()
    {
        return $this->listing->images()->create([
            'user_id' => $this->user->id,
            'filename' => $this->videoData['filename'],
            'privacy' => $this->videoData['privacy'],
        ]);
    }
}
