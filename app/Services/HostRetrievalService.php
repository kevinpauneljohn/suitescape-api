<?php

namespace App\Services;

use App\Models\User;

class HostRetrievalService
{
    protected ?User $currentHost = null;

    public function __construct(protected PriceCalculatorService $priceCalculatorService) {}

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
        $listings = $this->getHost($id)->listings()
            ->with([
                'images',
                'videos.sections',
            ])
            ->withAggregate('reviews', 'rating', 'avg')
            ->get();

        // Compute today's rate-aware lowest room price for room-based listings
        foreach ($listings as $listing) {
            if (! $listing->is_entire_place) {
                $listing->lowest_room_price = $this->priceCalculatorService->getMinRoomPriceForListing($listing->id);
            }
        }

        return $listings;
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
