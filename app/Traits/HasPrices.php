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
