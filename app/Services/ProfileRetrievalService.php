<?php

namespace App\Services;

class ProfileRetrievalService
{
    public function getProfile()
    {
        return auth()->user();
    }

    public function getLikedListings()
    {
        $user = auth()->user();

        return $user->likedListings->load([
            'listing' => fn ($query) => $query->withCount('views'),
            'listing.videos',
        ]);
    }

    public function getSavedListings()
    {
        $user = auth()->user();

        return $user->savedListings->load([
            'listing' => fn ($query) => $query->withCount('views'),
            'listing.videos',
        ]);
    }
}
