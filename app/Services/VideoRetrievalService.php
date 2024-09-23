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

        return Video::public()
            ->isTranscoded()
            ->isApproved()
            ->whereHas('listing', function ($query) use ($filters) {
                // Apply date filter
                $query->where(function ($query) use ($filters) {
                    $query->where('is_entire_place', true)
                        ->where(function ($query) use ($filters) {
                            $this->applyDateFilter($query, $filters);
                        });
                    $query->orWhere('is_entire_place', false)
                        ->whereHas('rooms', function ($query) use ($filters) {
                            $this->applyDateFilter($query, $filters);
                        });
                });

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
                return $query->orderByDesc();
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
            'max_price' => function ($query, $maxPrice) {
                $this->filterService->applyPriceFilter($query, $maxPrice);
            },
            'min_price' => function ($query, $minPrice) {
                $this->filterService->applyPriceFilter($query, null, $minPrice);
            },
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
            if (isset($filters[$filterKey])) {
                $filterFunction($query, $filters[$filterKey]);
            }
        }
    }

    private function applyDateFilter($query, $filters): void
    {
        if (isset($filters['check_in']) && isset($filters['check_out'])) {
            $this->filterService->applyUnavailableDateFilter($query, $filters['check_in'], $filters['check_out']);
        } else {
            $this->filterService->applyUnavailableDateFilter($query, today(), today()->addDay());
        }
    }
}
