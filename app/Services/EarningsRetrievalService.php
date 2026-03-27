<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Constant;
use App\Models\Listing;
use Illuminate\Support\Carbon;

class EarningsRetrievalService
{
    public function getYearlyEarnings(int $year, string $hostId, ?string $listingId = null): array
    {
        $monthlyEarnings = [];
        $totalGrossEarnings = 0;
        $totalSuitescapeFees = 0;
        $totalNetEarnings = 0;
        $totalBookingsCount = 0;

        $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfDay();

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

            $bookingsQuery = Booking::findByHostId($hostId)
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
                ->where('status', 'completed');

            // Get gross earnings (total booking amount)
            $grossEarnings = (clone $bookingsQuery)->sum('amount');
            
            // Get total fees deducted
            $fees = (clone $bookingsQuery)->sum('suitescape_fee');
            
            // Get net earnings (host_earnings column, or calculate if not set)
            $netEarnings = (clone $bookingsQuery)->sum('host_earnings');
            
            // Get bookings count for this month
            $bookingsCount = (clone $bookingsQuery)->count();
            
            // If host_earnings is 0 but amount > 0, use amount (for legacy bookings)
            if ($netEarnings == 0 && $grossEarnings > 0) {
                $netEarnings = $grossEarnings;
            }

            $totalGrossEarnings += $grossEarnings;
            $totalSuitescapeFees += $fees;
            $totalNetEarnings += $netEarnings;
            $totalBookingsCount += $bookingsCount;

            $monthlyEarnings[] = [
                'month' => $month,
                'earnings' => $netEarnings, // Host's net earnings
                'gross_earnings' => $grossEarnings,
                'suitescape_fee' => $fees,
                'bookings_count' => $bookingsCount,
            ];
        }

        // Get current fee rate from constants
        $currentFeeRate = 0;
        try {
            $feeConstant = Constant::where('key', 'suitescape_fee')->first();
            if ($feeConstant) {
                $currentFeeRate = (float) $feeConstant->value;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Get listing breakdown for the year (only if not filtering by specific listing)
        $listingBreakdown = [];
        if (!$listingId) {
            $listingBreakdown = $this->getListingBreakdown($year, $hostId);
        }

        return [
            'year' => $year,
            'monthly_earnings' => $monthlyEarnings,
            'summary' => [
                'total_gross_earnings' => $totalGrossEarnings,
                'total_suitescape_fees' => $totalSuitescapeFees,
                'total_net_earnings' => $totalNetEarnings,
                'total_bookings_count' => $totalBookingsCount,
                'current_fee_rate' => $currentFeeRate,
            ],
            'listing_breakdown' => $listingBreakdown,
        ];
    }

    /**
     * Get earnings breakdown by listing for a year
     */
    private function getListingBreakdown(int $year, string $hostId): array
    {
        $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfDay();

        // Get default fee rate from constants
        $defaultFeeRate = 0;
        try {
            $feeConstant = Constant::where('key', 'suitescape_fee')->first();
            if ($feeConstant) {
                $defaultFeeRate = (float) $feeConstant->value;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Get all listings for this host
        $listings = Listing::where('user_id', $hostId)->get(['id', 'name', 'custom_suitescape_fee', 'is_partner']);

        $breakdown = [];
        foreach ($listings as $listing) {
            $bookingsQuery = Booking::where('listing_id', $listing->id)
                ->where('status', 'completed')
                ->where(function ($query) use ($startOfYear, $endOfYear) {
                    $query->whereBetween('date_start', [$startOfYear, $endOfYear])
                        ->orWhereBetween('date_end', [$startOfYear, $endOfYear]);
                });

            $grossEarnings = (clone $bookingsQuery)->sum('amount');
            $fees = (clone $bookingsQuery)->sum('suitescape_fee');
            $netEarnings = (clone $bookingsQuery)->sum('host_earnings');
            $bookingsCount = (clone $bookingsQuery)->count();

            // Skip listings with no earnings
            if ($grossEarnings == 0 && $bookingsCount == 0) {
                continue;
            }

            // If host_earnings is 0 but amount > 0, use amount (for legacy bookings)
            if ($netEarnings == 0 && $grossEarnings > 0) {
                $netEarnings = $grossEarnings;
            }

            // Determine the fee rate for this listing
            $feeRate = $listing->custom_suitescape_fee !== null 
                ? (float) $listing->custom_suitescape_fee 
                : $defaultFeeRate;

            $breakdown[] = [
                'listing_id' => $listing->id,
                'listing_name' => $listing->name,
                'is_partner' => (bool) $listing->is_partner,
                'has_custom_fee' => $listing->custom_suitescape_fee !== null,
                'fee_rate' => $feeRate,
                'gross_earnings' => $grossEarnings,
                'suitescape_fee' => $fees,
                'net_earnings' => $netEarnings,
                'bookings_count' => $bookingsCount,
            ];
        }

        // Sort by net earnings descending
        usort($breakdown, function ($a, $b) {
            return $b['net_earnings'] <=> $a['net_earnings'];
        });

        return $breakdown;
    }

    public function getAvailableYears(string $hostId): array
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
