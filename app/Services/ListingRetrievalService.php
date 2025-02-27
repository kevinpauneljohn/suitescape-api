<?php

namespace App\Services;

use App\Models\Listing;

class ListingRetrievalService
{
    protected ChatService $chatService;

    protected ConstantService $constantService;

    protected FilterService $filterService;

    protected PriceCalculatorService $priceCalculatorService;

    protected UnavailableDateService $unavailableDateService;

    protected ?Listing $currentListing = null;

    public function __construct(ChatService $chatService, ConstantService $constantService, FilterService $filterService, PriceCalculatorService $priceCalculatorService, UnavailableDateService $unavailableDateService)
    {
        $this->chatService = $chatService;
        $this->constantService = $constantService;
        $this->filterService = $filterService;
        $this->priceCalculatorService = $priceCalculatorService;
        $this->unavailableDateService = $unavailableDateService;
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

    public function searchListings(?string $searchQuery, ?int $limit = 10)
    {
        return Listing::whereRaw('MATCH(name, location) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchQuery])
            ->uniqueByLocation()
            ->whereHas('videos', function ($query) {
                $query->isApproved();
            })
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

    /**
     * @throws \Exception
     */
    public function getListingDetails(string $id, array $filters = [])
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $listing = $this->getListing($id);
        $suitescapeCancellationPolicy = $this->constantService->getConstant('cancellation_policy')->value;

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
            'specialRates',
            'unavailableDates',
            'addons' => fn ($query) => $query->excludeNoStocks(),
        ])
            ->loadCount(['likes', 'saves', 'views', 'reviews'])
            ->loadAggregate('reviews', 'rating', 'avg');

        // Add the host response rate
        $hostResponseRate = $this->chatService->getHostResponseRate($listing->host->id);
        foreach ($hostResponseRate as $key => $value) {
            $listing->host->setAttribute($key, $value);
        }

        // Get current entire place price
        $listing->entire_place_price = $listing->getCurrentPrice($startDate, $endDate);

        // Get minimum price from room categories
        $listing->lowest_room_price = $this->priceCalculatorService->getMinRoomPriceForListing($id, $startDate, $endDate);

        // Add the suitescape cancellation policy
        $listing->cancellation_policy = $suitescapeCancellationPolicy;

        return $listing;
    }

    public function getListingRooms(string $id, array $filters = [])
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $query = $this->getListing($id)->rooms()
            ->excludeNoStocks()
             // Check if room is available for the given date range
            ->when(isset($startDate) && isset($endDate), function ($query) use ($startDate, $endDate) {
                $this->filterService->applyUnavailableDateFilter($query, $startDate, $endDate);
            })
            ->with([
                'roomCategory' => function ($query) use ($startDate, $endDate) {
                    return $this->priceCalculatorService->getPriceForRoomCategoriesToQuery($query, $startDate, $endDate);
                },
                'roomRule',
                'unavailableDates',
                'roomAmenities.amenity',
            ]);

        return $this->orderByRoomPrice($query, $startDate, $endDate)->get();
    }

    public function orderByRoomPrice($query, $startDate, $endDate)
    {
        $roomCategoriesSubquery = $this->priceCalculatorService->getPriceForRoomCategoriesSubquery($startDate, $endDate);

        // Compute price to order by it
        return $query->joinSub($roomCategoriesSubquery, 'priceSub', function ($join) {
            $join->on('rooms.room_category_id', '=', 'priceSub.room_category_id');
        })
            ->orderBy('priceSub.price') // Order by the computed price
            ->orderBy('rooms.id'); // Secondary sort by room ID for consistent ordering
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

    public function getUnavailableDatesFromRange(string $id, string $startDate, string $endDate)
    {
        return $this->unavailableDateService->getUnavailableDatesFromRange('listing', $id, $startDate, $endDate);
    }
}
