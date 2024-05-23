<?php

namespace App\Services;

use App\Models\Listing;

class ListingRetrievalService
{
    protected ConstantService $constantService;

    protected FilterService $filterService;

    protected ?Listing $currentListing = null;

    public function __construct(ConstantService $constantService, FilterService $filterService)
    {
        $this->constantService = $constantService;
        $this->filterService = $filterService;

        $this->currentListing = null;
    }

    public function getAllListings()
    {
        return Listing::all();
    }

    public function getListingsByHost(string $hostId)
    {
        // TIP:
        // With is best used for multiple data
        // Load is best used for single data
        return Listing::where('user_id', $hostId)
            ->with('images')
            ->withAggregate('reviews', 'rating', 'avg')
            ->orderByDesc('created_at')
            ->get();
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
        $suitescapeCancellationPolicy = $this->constantService->getConstant('cancellation_policy')->value;

        $listing = $this->getListing($id);

        // Load all images and videos if the authenticated user is the listing owner
        if (auth('sanctum')->id() === $listing->user_id) {
            $listing->load(['images', 'videos.sections']);
        } else {
            $listing->load(['publicImages', 'publicVideos.sections']);
        }

        // Load all other relationships
        $listing->load([
            'host',
            'serviceRatings',
            'reviews' => fn ($query) => $query->with('user')->take(10),
            'bookingPolicies',
            'listingNearbyPlaces.nearbyPlace',
            'unavailableDates',
            'addons' => fn ($query) => $query->excludeZeroQuantity(),
        ])
            ->loadCount(['likes', 'saves', 'views', 'reviews'])
            ->loadAggregate('roomCategories', 'price', 'min')
            ->loadAggregate('reviews', 'rating', 'avg');

        $listing->cancellation_policy = $suitescapeCancellationPolicy;

        return $listing;
    }

    public function getListingRooms(string $id, array $filters = [])
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        return $this->getListing($id)->rooms()
            ->excludeZeroQuantity()
            // Check if room is available for the given date range
            ->when(isset($startDate) && isset($endDate), function ($query) use ($startDate, $endDate) {
                $this->filterService->applyUnavailableDateFilter($query, $startDate, $endDate);
            })
            ->with([
                'roomCategory',
                'roomRule',
                'unavailableDates',
                'roomAmenities.amenity',
            ])
            // Order rooms by price
            ->join('room_categories', 'rooms.room_category_id', '=', 'room_categories.id')
            ->orderBy('room_categories.price')
            ->select('rooms.*') // Avoid column name collision
            ->get();
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
        return $this->getListing($id)->reviews->load(['user', 'listing.images']);
    }
}
