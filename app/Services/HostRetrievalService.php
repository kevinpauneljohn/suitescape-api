<?php

namespace App\Services;

use App\Models\User;

class HostRetrievalService
{
    protected ?User $currentHost = null;

    public function getAllHosts()
    {
        return User::all();
    }

    public function getHost(string $id)
    {
        if (! $this->currentHost || $this->currentHost->id !== $id) {
            $this->currentHost = User::findOrFail($id);
        }

        return $this->currentHost;
    }

    public function getHostDetails(string $id)
    {
        return $this->getHost($id)->load([
            'listings' => fn ($query) => $query->with('publicImages')->withAggregate('reviews', 'rating', 'avg'),
            'listingsReviews' => fn ($query) => $query->with(['user', 'listing.publicImages']),
        ])->loadCount(['listings', 'listingsLikes', 'listingsReviews'])->loadAvg('listingsReviews', 'rating');
    }

    public function getHostListings(string $id)
    {
        return $this->getHost($id)->listings;
    }

    public function getHostReviews(string $id)
    {
        return $this->getHost($id)->listingsReviews;
    }

    public function getHostLikes(string $id)
    {
        return $this->getHost($id)->listingsLikes;
    }

    public function getHostSaves(string $id)
    {
        return $this->getHost($id)->listingsSaves;
    }

    public function getHostViews(string $id)
    {
        return $this->getHost($id)->listingsViews;
    }
}
