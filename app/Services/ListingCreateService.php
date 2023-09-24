<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\RoomCategory;
use App\Models\User;

class ListingCreateService
{
    private String $filename;
    private array $videoData;
    private User $user;

    public function __construct(String $filename, array $videoData)
    {
        $this->filename = $filename;
        $this->videoData = $videoData;
        $this->user = auth()->user();
    }

    public function createListingVideo()
    {
        // Temporary solution for now so that video can be uploaded
        $listing = $this->createListing();

        return $listing->videos()->create([
            "user_id" => $this->user->id,
            "filename" => $this->filename,
            "privacy" => $this->videoData['privacy'],
        ]);
    }

    private function createListing(): Listing
    {
        $listing = $this->user->listings()->save(
            Listing::factory()->make()
        );

        $this->createRoomCategories($listing);

        return $listing;
    }

    private function createRoomCategories($listing): void
    {
        // Temporary solution so that the video listing has a price per night
        $listing->roomCategories()->saveMany(
            RoomCategory::factory(rand(1, 3))->make()
        );
    }
}
