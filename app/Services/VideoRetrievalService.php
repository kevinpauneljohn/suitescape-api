<?php

namespace App\Services;

use App\Models\Video;
use Exception;
use Micilini\VideoStream\VideoStream;

class VideoRetrievalService
{
    public function getAllVideos()
    {
        return Video::all();
    }

    public function getVideoFeed(array $filters = [])
    {
        $adults = $filters['adults'] ?? 0;
        $children = $filters['children'] ?? 0;
        $pax = $adults + $children;

        return Video::public()
            ->whereHas('listing', function ($query) use ($filters, $pax) {
                $query = $this->applyDestinationFilter($query, $filters);
                $query = $this->applyPaxFilter($query, $filters, $pax);
                $query = $this->applyPriceFilter($query, $filters);
                $query = $this->applyRatingFilter($query, $filters);
                $query = $this->applyAmenitiesFilter($query, $filters);

                return $this->applyRoomsFilter($query, $filters);
            })
            ->with(['listing' => function ($query) {
                $query->with('host')->withCount('likes')
                    ->withAggregate('roomCategories', 'price', 'min')
                    ->withAggregate('reviews', 'rating', 'avg');
            }])
            ->when(empty($filters), function ($query) {
                return $query->orderByDesc();
            }, function ($query) {
                return $query->orderByLowestPrice();
            })
            ->cursorPaginate(5);
    }

    public function getVideoUrl(Video $video)
    {
        //        return public_path('storage/videos/'.$video['filename']);
        return storage_path('app/public/videos/'.$video['filename']);
    }

    public function streamVideo(Video $video)
    {
        $videoUrl = $this->getVideoUrl($video);
        $options = [
            'is_localPath' => true,
        ];

        try {
            return (new VideoStream())->streamVideo($videoUrl, $options);
        } catch (Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 404);
        }
    }

    private function applyDestinationFilter($query, $filters)
    {
        return $query->when(isset($filters['destination']), function ($query) use ($filters) {
            return $query->whereRaw('MATCH(name, location) AGAINST(? IN NATURAL LANGUAGE MODE)', [$filters['destination']]);
        });
    }

    private function applyPaxFilter($query, $filters, $pax)
    {
        return $query->when(isset($filters['adults']) || isset($filters['children']), function ($query) use ($pax) {
            $query->whereHas('roomCategories', function ($query) use ($pax) {
                return $query->where('pax', '>=', $pax);
            });
        });
    }

    private function applyPriceFilter($query, $filters)
    {
        $query = $query->when(isset($filters['max_price']) && $filters['max_price'] >= 0, function ($query) use ($filters) {
            $query->whereDoesntHave('roomCategories', function ($query) use ($filters) {
                return $query->where('price', '>', $filters['max_price']);
            });
        });

        return $query->when(isset($filters['min_price']), function ($query) use ($filters) {
            $query->whereDoesntHave('roomCategories', function ($query) use ($filters) {
                return $query->where('price', '<', $filters['min_price']);
            });
        });
    }

    private function applyRatingFilter($query, $filters)
    {
        $query = $query->when(isset($filters['max_rating']) && $filters['max_rating'] >= 0, function ($query) use ($filters) {
            $query->whereHas('reviews', function ($query) use ($filters) {
                $query->select('listing_id')->groupBy('listing_id')->havingRaw('AVG(rating) <= ?', [$filters['max_rating']]);
            });
        });

        return $query->when(isset($filters['min_rating']), function ($query) use ($filters) {
            $query->whereHas('reviews', function ($query) use ($filters) {
                $query->select('listing_id')->groupBy('listing_id')->havingRaw('AVG(rating) >= ?', [$filters['min_rating']]);
            });
        });
    }

    private function applyAmenitiesFilter($query, $filters)
    {
        return $query->whereHas('rooms', function ($query) use ($filters) {
            $query->when(isset($filters['amenities']), function ($query) use ($filters) {
                return $query->whereHas('roomAmenities', function ($query) use ($filters) {
                    //                            return $query->whereHas('amenity', function ($query) use ($filters) {
                    //                                return $query->whereIn('name', $filters['amenities']);
                    //                            });
                    return $query->whereDoesntHave('amenity', function ($query) use ($filters) {
                        return $query->whereNotIn('name', $filters['amenities']);
                    });
                });
            });
        });
    }

    private function applyRoomsFilter($query, $filters)
    {
        return $query->when(isset($filters['rooms']), function ($query) use ($filters) {
            return $query->has('rooms', '>=', $filters['rooms']);
        });
    }
}
