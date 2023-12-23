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
        return $this->loadListings('likedListings');
    }

    public function getSavedListings()
    {
        return $this->loadListings('savedListings');
    }

    public function getViewedListings()
    {
        return $this->loadListings('viewedListings');
    }

    private function loadListings(string $relation)
    {
        $user = auth()->user();

        return $user->$relation->load([
            'listing' => fn ($query) => $query->withCount('views'),
            'listing.images',
            'listing.videos',
        ]);
    }
}
