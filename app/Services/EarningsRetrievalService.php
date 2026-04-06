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
        $totalGuestPaid = 0;       // Full amount guest paid (amount column)
        $totalHostGross = 0;       // Host's gross = amount - guest_service_fee - vat
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

            // Total amount guest paid (includes guest service fee + VAT)
            $guestPaid = (clone $bookingsQuery)->sum('amount');

            // Host gross = what the host is owed before the platform fee:
            // amount - guest_service_fee - vat  (those belong to Suitescape, not the host)
            $hostGross = (clone $bookingsQuery)->selectRaw(
                'SUM(amount - COALESCE(guest_service_fee, 0) - COALESCE(vat, 0)) as host_gross'
            )->value('host_gross') ?? 0;

            // Total Suitescape platform fee deducted from host
            $fees = (clone $bookingsQuery)->sum('suitescape_fee');

            // Net earnings for the host (host_gross - suitescape_fee)
            $netEarnings = (clone $bookingsQuery)->sum('host_earnings');

            // Get bookings count for this month
            $bookingsCount = (clone $bookingsQuery)->count();

            // Legacy fallback: if host_earnings not stored, use host_gross
            if ($netEarnings == 0 && $guestPaid > 0) {
                $netEarnings = $hostGross ?: $guestPaid;
            }
            if ($hostGross == 0 && $guestPaid > 0) {
                $hostGross = $guestPaid;
            }

            $totalGuestPaid += $guestPaid;
            $totalHostGross += $hostGross;
            $totalSuitescapeFees += $fees;
            $totalNetEarnings += $netEarnings;
            $totalBookingsCount += $bookingsCount;

            $monthlyEarnings[] = [
                'month' => $month,
                'earnings' => $netEarnings,       // Host's net earnings (what they take home)
                'gross_earnings' => $hostGross,   // Host gross (before platform fee)
                'guest_paid' => $guestPaid,        // Full guest payment (for reference)
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
                'total_guest_paid' => $totalGuestPaid,
                'total_gross_earnings' => $totalHostGross,   // Host gross (before platform fee)
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

            $guestPaid = (clone $bookingsQuery)->sum('amount');

            // Host gross = amount - guest_service_fee - vat (those belong to Suitescape)
            $hostGross = (clone $bookingsQuery)->selectRaw(
                'SUM(amount - COALESCE(guest_service_fee, 0) - COALESCE(vat, 0)) as host_gross'
            )->value('host_gross') ?? 0;

            $fees = (clone $bookingsQuery)->sum('suitescape_fee');
            $netEarnings = (clone $bookingsQuery)->sum('host_earnings');
            $bookingsCount = (clone $bookingsQuery)->count();

            // Skip listings with no earnings
            if ($guestPaid == 0 && $bookingsCount == 0) {
                continue;
            }

            // Legacy fallback: if host_earnings not stored, use host_gross or amount
            if ($netEarnings == 0 && $guestPaid > 0) {
                $netEarnings = $hostGross ?: $guestPaid;
            }
            if ($hostGross == 0 && $guestPaid > 0) {
                $hostGross = $guestPaid;
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
                'guest_paid' => $guestPaid,           // Full amount guest paid (reference)
                'gross_earnings' => $hostGross,        // Host gross before platform fee
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

    /**
     * Get detailed earnings and bookings for a specific listing
     */
    public function getListingEarningsDetails(string $listingId, int $year, string $hostId): array
    {
        // Verify the listing belongs to this host
        $listing = Listing::where('id', $listingId)
            ->where('user_id', $hostId)
            ->first(['id', 'name', 'custom_suitescape_fee', 'is_partner']);

        if (!$listing) {
            throw new \Exception('Listing not found or unauthorized');
        }

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

        // Get all completed bookings for this listing in the year
        $bookings = Booking::where('listing_id', $listingId)
            ->where('status', 'completed')
            ->where(function ($query) use ($startOfYear, $endOfYear) {
                $query->whereBetween('date_start', [$startOfYear, $endOfYear])
                    ->orWhereBetween('date_end', [$startOfYear, $endOfYear]);
            })
            ->with(['user:id,firstname,lastname,email,profile_image', 'rooms.roomCategory'])
            ->orderBy('date_start', 'desc')
            ->get();

        // Calculate totals
        $totalGuestPaid = $bookings->sum('amount');
        // Host gross = amount - guest_service_fee - vat (those belong to Suitescape, not the host)
        $totalHostGross = $bookings->sum(fn ($b) =>
            (float) $b->amount - (float) ($b->guest_service_fee ?? 0) - (float) ($b->vat ?? 0)
        );
        $totalSuitescapeFees = $bookings->sum('suitescape_fee');
        $totalNetEarnings = $bookings->sum('host_earnings');

        // Legacy fallback: if host_earnings not stored, use host_gross or amount
        if ($totalNetEarnings == 0 && $totalGuestPaid > 0) {
            $totalNetEarnings = $totalHostGross ?: $totalGuestPaid;
        }
        if ($totalHostGross == 0 && $totalGuestPaid > 0) {
            $totalHostGross = $totalGuestPaid;
        }

        // Determine the fee rate for this listing
        $feeRate = $listing->custom_suitescape_fee !== null 
            ? (float) $listing->custom_suitescape_fee 
            : $defaultFeeRate;

        // Format booking details
        $bookingDetails = $bookings->map(function ($booking) {
            $guestServiceFee = (float) ($booking->guest_service_fee ?? 0);
            $vat = (float) ($booking->vat ?? 0);
            $guestPaid = (float) $booking->amount;
            // Host gross = what the host earns before platform fee (excludes guest service fee + VAT)
            $hostGross = $guestPaid - $guestServiceFee - $vat;
            $suitescapeFee = (float) $booking->suitescape_fee;
            $hostEarnings = (float) $booking->host_earnings;

            // Legacy fallback
            if ($hostEarnings == 0 && $guestPaid > 0) {
                $hostEarnings = $hostGross ?: $guestPaid;
            }
            if ($hostGross == 0 && $guestPaid > 0) {
                $hostGross = $guestPaid;
            }

            return [
                'id' => $booking->id,
                'guest' => [
                    'id' => $booking->user->id ?? null,
                    'name' => $booking->user 
                        ? trim($booking->user->firstname . ' ' . $booking->user->lastname) 
                        : 'Unknown Guest',
                    'email' => $booking->user->email ?? null,
                    'profile_image' => $booking->user->profile_image_url ?? null,
                ],
                'dates' => [
                    'check_in' => $booking->date_start ? Carbon::parse($booking->date_start)->format('M d, Y') : null,
                    'check_out' => $booking->date_end ? Carbon::parse($booking->date_end)->format('M d, Y') : null,
                    'nights' => $booking->date_start && $booking->date_end 
                        ? Carbon::parse($booking->date_start)->diffInDays(Carbon::parse($booking->date_end)) 
                        : 0,
                ],
                'rooms' => $booking->rooms->map(function ($room) {
                    return [
                        'id' => $room->id,
                        'name' => $room->roomCategory->name ?? 'Room',
                    ];
                })->toArray(),
                'guests_count' => $booking->guests ?? 0,
                'price_breakdown' => [
                    'guest_paid'        => $guestPaid,       // Total paid by guest (incl. service fee + VAT)
                    'guest_service_fee' => $guestServiceFee, // Guest service fee (belongs to Suitescape)
                    'vat'               => $vat,             // VAT (belongs to government via Suitescape)
                    'gross_amount'      => $hostGross,       // Host gross = guest_paid - service_fee - vat
                    'suitescape_fee'    => $suitescapeFee,   // Platform fee deducted from host (e.g. 3%)
                    'host_earnings'     => $hostEarnings,    // Host net = gross_amount - suitescape_fee
                ],
                'booked_at' => $booking->created_at ? Carbon::parse($booking->created_at)->format('M d, Y h:i A') : null,
                'completed_at' => $booking->updated_at ? Carbon::parse($booking->updated_at)->format('M d, Y h:i A') : null,
            ];
        })->toArray();

        return [
            'listing' => [
                'id' => $listing->id,
                'name' => $listing->name,
                'is_partner' => (bool) $listing->is_partner,
                'has_custom_fee' => $listing->custom_suitescape_fee !== null,
                'fee_rate' => $feeRate,
            ],
            'year' => $year,
            'summary' => [
                'total_bookings' => $bookings->count(),
                'total_guest_paid' => $totalGuestPaid,
                'total_gross_earnings' => $totalHostGross,     // Host gross (before platform fee)
                'total_suitescape_fees' => $totalSuitescapeFees,
                'total_net_earnings' => $totalNetEarnings,
            ],
            'bookings' => $bookingDetails,
        ];
    }
}
