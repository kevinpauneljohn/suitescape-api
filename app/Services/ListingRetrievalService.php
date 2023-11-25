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
        if ($this->currentListing && $this->currentListing->id === $id) {
            return $this->currentListing;
        }

        $this->currentListing = Listing::findOrFail($id);
        return $this->currentListing;
    }

    public function getListingComplete(string $id)
    {
        return $this->getListing($id)->load([
            'host',
            'images',
            'videos',
            'reviews',
        ]);
    }

    public function getListingHost(string $id)
    {
        return $this->getListing($id)->host;
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
