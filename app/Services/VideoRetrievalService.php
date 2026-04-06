<?php

namespace App\Services;

use App\Models\Video;
use Exception;
use Illuminate\Support\Facades\Storage;
use Micilini\VideoStream\VideoStream;

class VideoRetrievalService
{
    protected FilterService $filterService;

    protected PriceCalculatorService $priceCalculatorService;

    public function __construct(FilterService $filterService, PriceCalculatorService $priceCalculatorService)
    {
        $this->filterService = $filterService;
        $this->priceCalculatorService = $priceCalculatorService;
    }

    public function getAllVideos()
    {
        return Video::all();
    }

    public function getVideoPath(string $filename): string
    {
        //        return public_path('storage/videos/'.$filename);
        //        return storage_path('app/public/videos/'.$filename);
        return Storage::disk('public')->path('videos/'.$filename);
    }

    public function streamVideo(Video $video)
    {
        $videoPath = $this->getVideoPath($video->filename);

        $options = [
            'is_localPath' => true,
        ];

        try {
            return (new VideoStream)->streamVideo($videoPath, $options);
        } catch (Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 404);
        }
    }

    public function getVideoFeed(array $filters = [])
    {
        //        $adults = $filters['adults'] ?? 0;
        //        $children = $filters['children'] ?? 0;
        //        $pax = $adults + $children;

        // Get the current authenticated user (if any)
        $currentUser = auth('sanctum')->user();

        return Video::public()
            ->isTranscoded()
            ->isApproved()
            ->whereHas('listing', function ($query) use ($filters, $currentUser) {
                // Exclude listings owned by the current user
                if ($currentUser) {
                    $query->where('user_id', '!=', $currentUser->id);
                }

                // Apply date filter - different logic for entire place vs room-based
                $query->where(function ($query) use ($filters) {
                    // For entire place listings, check listing-level availability
                    $query->where('is_entire_place', true)
                        ->where(function ($query) use ($filters) {
                            $this->applyListingDateFilter($query, $filters);
                        });
                    // For room-based listings, check room-level availability
                    $query->orWhere('is_entire_place', false)
                        ->whereHas('rooms', function ($query) use ($filters) {
                            $this->applyRoomDateFilter($query, $filters);
                        });
                });

                // Apply booking availability filter at listing level (works for both types)
                $this->applyBookingFilter($query, $filters);

                // Apply main filters
                $this->applyMainFilters($query, $filters);
            })
            ->with(['listing' => function ($query) use ($filters) {
                $checkIn = $filters['check_in'] ?? null;
                $checkOut = $filters['check_out'] ?? null;

                $query->with('host')->withCount('likes')
                    ->withAggregate('reviews', 'rating', 'avg')
                    ->addSelect([
                        'lowest_room_price' => $this->priceCalculatorService->getMinRoomPriceForListingsSubquery($checkIn, $checkOut),
                    ]);

                return $this->priceCalculatorService->getEntirePriceForListingsToQuery($query, $checkIn, $checkOut);
            }, 'sections'])
            ->when(empty($filters), function ($query) {
                // Use pre-computed random_order column for randomized feed
                // This column is shuffled daily via scheduled task (videos:shuffle-order)
                // On pull-to-refresh, frontend clears videos and fetches fresh - 
                // combined with daily shuffle, this provides variety
                return $query->orderBy('videos.random_order')->orderBy('videos.id');
            }, function ($query) {
                return $this->orderByListingType($query);
            })
            ->cursorPaginate(5);
    }

    public function orderByListingType($query)
    {
        $priceSubquery = $this->priceCalculatorService->getMinPriceByTypeSubquery();

        // Use the subquery in a join to add the lowestPrice to each video
        return $query->select('videos.*', 'priceSub.min_price')
            ->joinSub($priceSubquery, 'priceSub', function ($join) {
                $join->on('videos.listing_id', '=', 'priceSub.listing_id');
            })
            ->orderBy('priceSub.min_price')  // Order by the computed lowest price
            ->orderBy('videos.id');            // Secondary sort by video ID for consistent ordering
    }

    private function initializeFilterMethods(): array
    {
        return [
            'destination' => function ($query, $destination) {
                $this->filterService->applyDestinationFilter($query, $destination);
            },
            'adults' => function ($query, $adultCount) {
                $this->filterService->applyAdultCountFilter($query, $adultCount);
            },
            'children' => function ($query, $childrenCount) {
                $this->filterService->applyChildrenCountFilter($query, $childrenCount);
            },
            'facilities' => function ($query, $facilities) {
                $this->filterService->applyFacilityTypeFilter($query, $facilities);
            },
            // min_price and max_price are handled together in applyMainFilters
            // so that both bounds are applied in a single coherent query clause.
            // Individual entries are intentionally omitted here.
            'max_price' => null,
            'min_price' => null,
            'max_rating' => function ($query, $maxRating) {
                $this->filterService->applyRatingFilter($query, $maxRating);
            },
            'min_rating' => function ($query, $minRating) {
                $this->filterService->applyRatingFilter($query, null, $minRating);
            },
            'amenities' => function ($query, $amenities) {
                $this->filterService->applyAmenitiesFilter($query, $amenities);
            },
            'rooms' => function ($query, $rooms) {
                $this->filterService->applyRoomCountFilter($query, $rooms);
            },
        ];
    }

    private function applyMainFilters($query, $filters): void
    {
        $filterMethods = $this->initializeFilterMethods();

        foreach ($filterMethods as $filterKey => $filterFunction) {
            if ($filterFunction !== null && isset($filters[$filterKey])) {
                $filterFunction($query, $filters[$filterKey]);
            }
        }

        // Apply min/max price together so both bounds are enforced in one coherent clause
        $minPrice = isset($filters['min_price']) ? (float) $filters['min_price'] : null;
        $maxPrice = isset($filters['max_price']) ? (float) $filters['max_price'] : null;
        if ($minPrice !== null || $maxPrice !== null) {
            $this->filterService->applyPriceFilter($query, $maxPrice, $minPrice);
        }
    }

        /**
     * Apply date filter for entire place listings (query context is Listing)
     */
    private function applyListingDateFilter($query, $filters): void
    {
        $checkIn = $filters['check_in'] ?? null;
        $checkOut = $filters['check_out'] ?? null;

        // If no dates specified, use today + 1 day as default for availability check
        if (!$checkIn || !$checkOut) {
            $checkIn = today();
            $checkOut = today()->addDay();
        }

        // Apply the unavailable date filter for listings
        $this->filterService->applyUnavailableDateFilter($query, $checkIn, $checkOut);
    }

    /**
     * Apply date filter for room-based listings (query context is Room)
     */
    private function applyRoomDateFilter($query, $filters): void
    {
        $checkIn = $filters['check_in'] ?? null;
        $checkOut = $filters['check_out'] ?? null;

        // If no dates specified, use today + 1 day as default for availability check
        if (!$checkIn || !$checkOut) {
            $checkIn = today();
            $checkOut = today()->addDay();
        }

        // Apply the unavailable date filter for rooms
        $this->filterService->applyRoomUnavailableDateFilter($query, $checkIn, $checkOut);
    }

    /**
     * Apply booking availability filter (query context is Listing)
     */
    private function applyBookingFilter($query, $filters): void
    {
        $checkIn = $filters['check_in'] ?? null;
        $checkOut = $filters['check_out'] ?? null;

        // If no dates specified, use today + 1 day as default
        if (!$checkIn || !$checkOut) {
            $checkIn = today();
            $checkOut = today()->addDay();
        }

        // Apply the booking availability filter
        $this->filterService->applyBookingAvailabilityFilter($query, $checkIn, $checkOut);
    }
}
