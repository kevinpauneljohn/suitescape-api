<?php

namespace App\Services;

use App\Models\Listing;

class ListingCreateService
{
    private Listing $listing;

    private array $videoData;

    public function __construct(string $listingId, string $filename, array $videoData)
    {
        $videoData['filename'] = $filename;

        $this->listing = Listing::findOrFail($listingId);
        $this->videoData = $videoData;
    }

    public function createListingVideo()
    {
        return $this->listing->videos()->create([
            'user_id' => auth()->user()->id,
            'filename' => $this->videoData['filename'],
            'privacy' => $this->videoData['privacy'],
        ]);
    }

    public function createListingImage()
    {
        return $this->listing->images()->create([
            'user_id' => auth()->user()->id,
            'filename' => $this->videoData['filename'],
            'privacy' => $this->videoData['privacy'],
        ]);
    }
}
