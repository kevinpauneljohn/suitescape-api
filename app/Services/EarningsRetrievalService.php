<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;

class EarningsRetrievalService
{
    public function getYearlyEarnings(int $year, string $hostId, ?string $listingId = null)
    {
        $monthlyEarnings = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

            $earnings = Booking::findByHostId($hostId)
                ->whereHas('listing', function ($query) use ($listingId) {
                    $query->when($listingId, function ($query) use ($listingId) {
                        $query->where('id', $listingId);
                    });
                })
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date_start', [$startDate, $endDate])
                        ->orWhereBetween('date_end', [$startDate, $endDate])
                        ->orWhere(function ($query) use ($startDate, $endDate) {
                            $query->where('date_start', '<', $startDate)
                                ->where('date_end', '>', $endDate);
                        });
                })
                ->sum('amount');

            $monthlyEarnings[] = [
                'month' => $month,
                'earnings' => $earnings,
            ];
        }

        return [
            'year' => $year,
            'monthly_earnings' => $monthlyEarnings,
        ];
    }

    public function getAvailableYears(string $hostId)
    {
        return Booking::findByHostId($hostId)
            ->selectRaw('DISTINCT YEAR(date_start) AS year')
            ->union(
                Booking::findByHostId($hostId)
                    ->selectRaw('DISTINCT YEAR(date_end) AS year')
            )
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
    }
}
