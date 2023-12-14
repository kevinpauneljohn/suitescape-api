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

    public function getListing(string $id)
    {
        if (! $this->currentListing || $this->currentListing->id !== $id) {
            $this->currentListing = Listing::findOrFail($id);
        }

        return $this->currentListing
            ->load([
                'host',
                'serviceRatings',
                'reviews.user',
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
        ])->loadAggregate('reviews', 'rating', 'avg');
    }

    public function getListingHost(string $id)
    {
        return $this->getListing($id)->host->load([
            'listings' => function ($query) {
                $query->with(['reviews' => function ($query) {
                    $query->with(['user', 'room.roomCategory', 'listing.images']);
                }])
                    ->withCount(['reviews', 'likes'])
                    ->withAggregate('reviews', 'rating', 'avg');
            },
        ])->loadCount('listings');
    }

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
        return $this->getListing($id)->reviews;
    }
}
