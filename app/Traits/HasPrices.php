<?php

namespace App\Traits;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

trait HasPrices
{
    public function getCurrentPrice($startDate = null, $endDate = null)
    {
        $specialRate = $this->getCurrentSpecialRate($startDate, $endDate);

        if ($specialRate) {
            return $specialRate->price;
        }

        // Default to the current price column based on the start date
        return $this->getCurrentBasePrice($startDate);
    }

    public function getCurrentBasePrice($date = null)
    {
        $priceColumn = self::getCurrentPriceColumn($date);

        return $this->{$priceColumn};
    }

    public function getCurrentSpecialRate($startDate = null, $endDate = null)
    {
        $startDateToUse = $startDate ? Carbon::parse($startDate) : Carbon::now();
        $endDateToUse = $endDate ? Carbon::parse($endDate) : $startDateToUse;

        return $this->specialRates()
            ->where('start_date', '<=', $startDateToUse)
            ->where('end_date', '>=', $endDateToUse)
            ->first();
    }

    /**
     * Calculate the total price for a date range by iterating per-night.
     * Each night checks for a matching special rate first, then falls back
     * to the weekday/weekend base price for that specific date.
     *
     * This matches the mobile's calculateEntirePlacePrice / calculateNormalPrice logic.
     *
     * @param string|null $startDate  Check-in date (YYYY-MM-DD)
     * @param string|null $endDate    Check-out date (YYYY-MM-DD)
     * @return float  Total price across all nights
     */
    public function calculateTotalForDateRange($startDate, $endDate): float
    {
        if (!$startDate || !$endDate) {
            return (float) $this->getCurrentPrice($startDate, $endDate);
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        // If same day or invalid range, return a single night's price
        if ($start->gte($end)) {
            return (float) $this->getPriceForDate($start);
        }

        // Eager-load all special rates for this entity that overlap the date range.
        // A special rate overlaps if its start_date <= (last check-in night) AND end_date >= start
        $lastNight = $end->copy()->subDay();
        $specialRates = $this->specialRates()
            ->where('start_date', '<=', $lastNight)
            ->where('end_date', '>=', $start)
            ->get();

        $total = 0.0;
        $current = $start->copy();

        while ($current->lt($end)) {
            // Check if any special rate covers this specific night
            $matchingRate = $specialRates->first(function ($rate) use ($current) {
                $rateStart = Carbon::parse($rate->start_date)->startOfDay();
                $rateEnd = Carbon::parse($rate->end_date)->startOfDay();
                return $current->gte($rateStart) && $current->lte($rateEnd);
            });

            if ($matchingRate) {
                $total += (float) $matchingRate->price;
            } else {
                $total += (float) $this->getCurrentBasePrice($current);
            }

            $current->addDay();
        }

        return $total;
    }

    /**
     * Get the price for a single specific date (special rate or base weekday/weekend).
     */
    public function getPriceForDate($date): float
    {
        $carbonDate = Carbon::parse($date)->startOfDay();

        // Check for a special rate covering this specific date
        $specialRate = $this->specialRates()
            ->where('start_date', '<=', $carbonDate)
            ->where('end_date', '>=', $carbonDate)
            ->first();

        if ($specialRate) {
            return (float) $specialRate->price;
        }

        return (float) $this->getCurrentBasePrice($carbonDate);
    }

    public static function getCurrentPriceColumn($date = null)
    {
        // Parse the provided date or use the current date if none is provided
        $carbonDate = $date ? Carbon::parse($date) : Carbon::now();

        // Check if the day of the week is a weekend (Friday, Saturday, or Sunday)
        $isWeekend = in_array($carbonDate->dayOfWeek, [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY, CarbonInterface::SUNDAY]);

        // Return the appropriate price column based on whether it's a weekend or not
        return $isWeekend ? static::weekendPriceColumn() : static::weekdayPriceColumn();
    }

    abstract protected static function weekendPriceColumn();

    abstract protected static function weekdayPriceColumn();
}
