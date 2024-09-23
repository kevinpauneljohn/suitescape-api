<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Support\Carbon;

class PriceCalculatorService
{
    public function getEntirePriceForListingsToQuery($query, $startDate = null, $endDate = null)
    {
        $listingPriceColumn = Listing::getCurrentPriceColumn($startDate);
        $startDate = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::now()->toDateString();
        $endDate = $endDate ? Carbon::parse($endDate)->toDateString() : $startDate;

        return $query->selectRaw("COALESCE((SELECT price FROM special_rates WHERE special_rates.listing_id = listings.id AND start_date <= ? AND end_date >= ? ORDER BY price ASC LIMIT 1), $listingPriceColumn) as entire_place_price", [$startDate, $endDate]);
    }

    public function getPriceForRoomCategoriesToQuery($query, $startDate = null, $endDate = null)
    {
        $roomPriceColumn = RoomCategory::getCurrentPriceColumn($startDate);
        $startDate = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::now()->toDateString();
        $endDate = $endDate ? Carbon::parse($endDate)->toDateString() : $startDate;

        return $query->join('rooms', 'rooms.room_category_id', '=', 'room_categories.id')
            ->select('room_categories.*')
            ->selectRaw("COALESCE((SELECT price FROM special_rates WHERE special_rates.room_id = rooms.id AND start_date <= ? AND end_date >= ? ORDER BY price ASC LIMIT 1), room_categories.$roomPriceColumn) as price", [$startDate, $endDate]);
    }

    public function getMinRoomPriceForListingsSubquery($startDate = null, $endDate = null)
    {
        $roomPriceColumn = RoomCategory::getCurrentPriceColumn($startDate);
        $startDate = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::now()->toDateString();
        $endDate = $endDate ? Carbon::parse($endDate)->toDateString() : $startDate;

        return Room::whereColumn('rooms.listing_id', 'listings.id')
            ->selectRaw("MIN(COALESCE((SELECT price FROM special_rates WHERE special_rates.room_id = rooms.id AND start_date <= ? AND end_date >= ? ORDER BY price ASC LIMIT 1), room_categories.$roomPriceColumn))", [$startDate, $endDate])
            ->join('room_categories', 'rooms.room_category_id', '=', 'room_categories.id');
    }

    public function getPriceForRoomCategoriesSubquery($startDate = null, $endDate = null)
    {
        $roomPriceColumn = RoomCategory::getCurrentPriceColumn($startDate);
        $startDate = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::now()->toDateString();
        $endDate = $endDate ? Carbon::parse($endDate)->toDateString() : $startDate;

        return Room::join('room_categories', 'rooms.room_category_id', '=', 'room_categories.id')
            ->select('room_categories.id as room_category_id')
            ->selectRaw("COALESCE((SELECT price FROM special_rates WHERE special_rates.room_id = rooms.id AND start_date <= ? AND end_date >= ? ORDER BY price ASC LIMIT 1), room_categories.$roomPriceColumn) as price", [$startDate, $endDate]);
    }

    public function getMinPriceByTypeSubquery($startDate = null, $endDate = null)
    {
        $listingPriceColumn = Listing::getCurrentPriceColumn($startDate);
        $roomPriceColumn = RoomCategory::getCurrentPriceColumn();
        $startDate = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::now()->toDateString();
        $endDate = $endDate ? Carbon::parse($endDate)->toDateString() : $startDate;

        // Define the subquery to calculate the lowest price for each listing
        return Listing::select('id as listing_id')
            ->selectRaw("CASE
                WHEN is_entire_place = 1 THEN COALESCE((SELECT price FROM special_rates WHERE special_rates.listing_id = listings.id AND start_date <= ? AND end_date >= ? ORDER BY price ASC LIMIT 1), $listingPriceColumn)
                ELSE COALESCE((SELECT MIN(price) FROM special_rates WHERE special_rates.room_id IN (SELECT id FROM rooms WHERE rooms.listing_id = listings.id) AND start_date <= ? AND end_date >= ?), (SELECT MIN($roomPriceColumn) FROM room_categories WHERE room_categories.listing_id = listings.id))
                END AS min_price", [$startDate, $endDate, $startDate, $endDate]);
    }

    public function getMinRoomPriceForListing($listingId, $startDate = null, $endDate = null)
    {
        $roomPriceColumn = RoomCategory::getCurrentPriceColumn($startDate);
        $startDate = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::now()->toDateString();
        $endDate = $endDate ? Carbon::parse($endDate)->toDateString() : $startDate;

        return Room::whereHas('roomCategory', function ($query) use ($listingId) {
            $query->where('listing_id', $listingId);
        })
            ->join('room_categories', 'rooms.room_category_id', '=', 'room_categories.id')
            ->selectRaw("MIN(COALESCE((SELECT price FROM special_rates WHERE special_rates.room_id = rooms.id AND start_date <= ? AND end_date >= ? ORDER BY price ASC LIMIT 1), room_categories.$roomPriceColumn)) as min_price", [$startDate, $endDate])
            ->value('min_price');
    }
}
