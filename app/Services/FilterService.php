<?php

namespace App\Services;

class FilterService
{
    public function applyDestinationFilter($query, $destination)
    {
        return $query->whereRaw('MATCH(name, location) AGAINST(? IN NATURAL LANGUAGE MODE)', [$destination]);
    }

    public function applyAdultCountFilter($query, $adultCount)
    {
        return $query->where('adult_capacity', '>=', $adultCount);
    }

    public function applyChildrenCountFilter($query, $childrenCount)
    {
        return $query->where('child_capacity', '>=', $childrenCount);
    }

    //    private function applyPaxFilter($query, $filters, $pax)
    //    {
    //        return $query->when(isset($filters['adults']) || isset($filters['children']), function ($query) use ($pax) {
    //            $query->whereHas('roomCategories', function ($query) use ($pax) {
    //                return $query->where('pax', '>=', $pax);
    //            });
    //        });
    //    }

    public function applyFacilityTypeFilter($query, $facilities)
    {
        return $query->whereIn('facility_type', $facilities);
    }

    public function applyPriceFilter($query, $maxPrice = null, $minPrice = null)
    {
        // Apply max price filter
        $query->when($maxPrice && $maxPrice >= 0, function ($query) use ($maxPrice) {
            $query->where(function ($query) use ($maxPrice) {
                $query->where('is_entire_place', true)
                    ->where('entire_place_price', '<=', $maxPrice);
                $query->orWhere('is_entire_place', false)
                    ->whereDoesntHave('roomCategories', function ($query) use ($maxPrice) {
                        return $query->where('price', '>', $maxPrice);
                    });
                //                    ->whereHas('roomCategories', function ($query) use ($maxPrice) {
                //                        return $query->where('price', '<=', $maxPrice);
                //                    });
            });
        });

        // Apply min price filter
        $query->when($minPrice, function ($query) use ($minPrice) {
            $query->where(function ($query) use ($minPrice) {
                $query->where('is_entire_place', true)
                    ->where('entire_place_price', '>=', $minPrice);
                $query->orWhere('is_entire_place', false)
                    ->whereDoesntHave('roomCategories', function ($query) use ($minPrice) {
                        return $query->where('price', '<', $minPrice);
                    });
                //                    ->whereHas('roomCategories', function ($query) use ($minPrice) {
                //                        return $query->where('price', '>=', $minPrice);
                //                    });
            });
        });

        return $query;
    }

    public function applyRatingFilter($query, $maxRating = null, $minRating = null)
    {
        // If no rating filters are provided, return the query as is
        if (($maxRating === null || $maxRating <= 0) && ($minRating === null || $minRating <= 0)) {
            return $query;
        }

        return $query->whereHas('reviews', function ($query) use ($minRating, $maxRating) {
            $query->select('listing_id')
                ->selectRaw('AVG(rating) as average_rating')
                ->groupBy('listing_id');

            // Apply max rating filter
            $query->when($maxRating && $maxRating >= 0, function ($query) use ($maxRating) {
                $query->having('average_rating', '<=', $maxRating);
            });

            // Apply min rating filter
            $query->when($minRating, function ($query) use ($minRating) {
                $query->having('average_rating', '>=', $minRating);
            });
        });
    }

    public function applyAmenitiesFilter($query, $amenities)
    {
        // If no amenities are provided, return the query as is
        if (empty($amenities)) {
            return $query;
        }

        return $query->whereHas('rooms', function ($query) use ($amenities) {
            return $query->whereHas('roomAmenities', function ($query) use ($amenities) {
                //                            return $query->whereHas('amenity', function ($query) use ($filters) {
                //                                return $query->whereIn('name', $filters['amenities']);
                //                            });

                // Exclude rooms that don't have the selected amenities
                return $query->whereDoesntHave('amenity', function ($query) use ($amenities) {
                    return $query->whereNotIn('name', $amenities);
                });
            });
        });
    }

    public function applyRoomCountFilter($query, $roomCount)
    {
        return $query->has('rooms', '>=', $roomCount);
    }

    public function applyUnavailableDateFilter($query, $startDate, $endDate)
    {
        return $query->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->where(function ($query) use ($startDate, $endDate) {

                // Criteria for excluding rooms that are unavailable
                $query->whereDoesntHave('unavailableDates', function ($query) use ($startDate, $endDate) {
                    // Exclude rooms that are unavailable on the user's selected dates
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])

                        // Exclude rooms that are unavailable during the entire range of the user's selected dates
                        ->orWhere(function ($query) use ($startDate, $endDate) {
                            // Checks if the unavailability range overlaps with the user's selected range
                            $query->where('start_date', '<=', $endDate)
                                ->where('end_date', '>=', $startDate);
                        });
                });
            });
        });
    }
}
