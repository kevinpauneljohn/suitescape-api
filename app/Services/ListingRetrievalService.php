<?php

namespace App\Services;

use App\Models\Listing;

class ListingRetrievalService
{
    protected ?Listing $currentListing = null;

    public function getAllListings()
    {
        return Listing::all();
    }

    public function searchListings(?string $query, ?int $limit = 10)
    {
        return Listing::whereRaw('MATCH(name, location) AGAINST(? IN NATURAL LANGUAGE MODE)', [$query])
            ->limit($limit)
            ->get();
    }

    public function getListing(string $id)
    {
        if (! $this->currentListing || $this->currentListing->id !== $id) {
            $this->currentListing = Listing::findOrFail($id);
        }

        return $this->currentListing;
    }

    public function getListingDetails(string $id)
    {
        return $this->getListing($id)
            ->load([
                'host',
                'serviceRatings',
                'reviews' => fn ($query) => $query->with('user')->take(10),
                'images',
                'videos',
                'bookingPolicies',
                'nearbyPlaces'])
            ->loadCount(['likes', 'saves', 'views', 'reviews'])
            ->loadAggregate('roomCategories', 'price', 'min')
            ->loadAggregate('reviews', 'rating', 'avg');
    }

    public function getListingRooms(string $id)
    {
        return $this->getListing($id)->rooms->load([
            'roomCategory',
            'roomAmenities',
            'roomAmenities.amenity',
        ])->loadAggregate('reviews', 'rating', 'avg')->sortBy('price');
    }

    //    public function getListingHost(string $id)
    //    {
    //        return $this->getListing($id)->host->load([
    //            'listings' => function ($query) {
    //                $query->with(['images', 'reviews' => function ($query) {
    //                    $query->with(['user', 'room.roomCategory', 'listing.images']);
    //                }])
    //                    ->withCount(['reviews', 'likes'])
    //                    ->withAggregate('reviews', 'rating', 'avg');
    //            },
    //        ])->loadCount('listings');
    //    }

    public function getListingImages(string $id)
    {
        return $this->getListing($id)->images;
    }

    public function getListingVideos(string $id)
    {
        return $this->getListing($id)->videos;
    }

    public function getListingReviews(string $id)
    {
        return $this->getListing($id)->reviews->load(['user', 'room.roomCategory', 'listing.images']);
    }
}
