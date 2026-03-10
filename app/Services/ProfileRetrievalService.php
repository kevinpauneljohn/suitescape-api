<?php

namespace App\Services;

class ProfileRetrievalService
{
    protected PriceCalculatorService $priceCalculatorService;

    public function __construct(PriceCalculatorService $priceCalculatorService)
    {
        $this->priceCalculatorService = $priceCalculatorService;
    }

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

        $listings = $user->$relation->load([
            'listing' => fn ($query) => $query->withCount('views'),
            'listing.images',
            'listing.videos',
        ]);

        // Calculate price for each listing based on type
        foreach ($listings as $item) {
            if ($item->listing) {
                if ($item->listing->is_entire_place) {
                    // For entire place listings, calculate entire_place_price
                    $item->listing->entire_place_price = $item->listing->getCurrentPrice();
                } else {
                    // For room-based listings, calculate lowest_room_price
                    $item->listing->lowest_room_price = $this->priceCalculatorService->getMinRoomPriceForListing($item->listing->id);
                }
            }
        }

        return $listings;
    }
}
