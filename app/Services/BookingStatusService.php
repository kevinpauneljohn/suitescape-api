<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BookingStatusService
{
    /**
     * Update all booking statuses system-wide (for scheduled job)
     * This is time-aware and considers listing check-in/check-out times
     */
    public function updateBookingStatuses(): void
    {
        $now = Carbon::now();
        
        $this->updateToOngoingWithTime($now);
        $this->updateToCompletedWithTime($now);
        $this->updateMissedBookingsToCompleted($now);
    }

    /**
     * Update bookings for a specific user (time-aware)
     */
    public function updateBookingStatusesForUser($userId): void
    {
        $now = Carbon::now();
        
        // Get user's bookings that need status check
        $this->updateUserBookingsToOngoing($userId, $now);
        $this->updateUserBookingsToCompleted($userId, $now);
        $this->updateUserMissedBookings($userId, $now);
    }

    /**
     * Update bookings for a specific host's listings (time-aware)
     */
    public function updateBookingStatusesForHost($hostId): void
    {
        $now = Carbon::now();
        $listingIds = \App\Models\Listing::where('user_id', $hostId)->pluck('id');

        if ($listingIds->isEmpty()) {
            return;
        }

        $this->updateHostBookingsToOngoing($listingIds, $now);
        $this->updateHostBookingsToCompleted($listingIds, $now);
        $this->updateHostMissedBookings($listingIds, $now);
    }

    /**
     * Update upcoming → ongoing based on check-in time
     * Booking becomes "ongoing" when: date_start + check_in_time <= now
     */
    private function updateToOngoingWithTime(Carbon $now): void
    {
        $bookings = Booking::with('listing')
            ->where('status', 'upcoming')
            ->whereDate('date_start', '<=', $now->toDateString())
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            if ($this->shouldBeOngoing($booking, $now)) {
                $booking->update(['status' => 'ongoing']);
                $count++;
            }
        }

        if ($count > 0) {
            Log::info("Auto-updated {$count} booking(s) from upcoming to ongoing (time-aware)");
        }
    }

    /**
     * Update ongoing → completed based on check-out time
     * Booking becomes "completed" when: date_end + check_out_time <= now
     */
    private function updateToCompletedWithTime(Carbon $now): void
    {
        $bookings = Booking::with('listing')
            ->where('status', 'ongoing')
            ->whereDate('date_end', '<=', $now->toDateString())
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            if ($this->shouldBeCompleted($booking, $now)) {
                $booking->update(['status' => 'completed']);
                $count++;
            }
        }

        if ($count > 0) {
            Log::info("Auto-updated {$count} booking(s) from ongoing to completed (time-aware)");
        }
    }

    /**
     * Handle bookings that were missed entirely
     */
    private function updateMissedBookingsToCompleted(Carbon $now): void
    {
        $bookings = Booking::with('listing')
            ->where('status', 'upcoming')
            ->whereDate('date_end', '<', $now->toDateString())
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update(['status' => 'completed']);
            $count++;
        }

        if ($count > 0) {
            Log::info("Auto-updated {$count} missed booking(s) from upcoming to completed");
        }
    }

    /**
     * User-specific: Update upcoming → ongoing
     */
    private function updateUserBookingsToOngoing($userId, Carbon $now): void
    {
        $bookings = Booking::with('listing')
            ->where('user_id', $userId)
            ->where('status', 'upcoming')
            ->whereDate('date_start', '<=', $now->toDateString())
            ->get();

        foreach ($bookings as $booking) {
            if ($this->shouldBeOngoing($booking, $now)) {
                $booking->update(['status' => 'ongoing']);
            }
        }
    }

    /**
     * User-specific: Update ongoing → completed
     */
    private function updateUserBookingsToCompleted($userId, Carbon $now): void
    {
        $bookings = Booking::with('listing')
            ->where('user_id', $userId)
            ->where('status', 'ongoing')
            ->whereDate('date_end', '<=', $now->toDateString())
            ->get();

        foreach ($bookings as $booking) {
            if ($this->shouldBeCompleted($booking, $now)) {
                $booking->update(['status' => 'completed']);
            }
        }
    }

    /**
     * User-specific: Handle missed bookings
     */
    private function updateUserMissedBookings($userId, Carbon $now): void
    {
        Booking::where('user_id', $userId)
            ->where('status', 'upcoming')
            ->whereDate('date_end', '<', $now->toDateString())
            ->update(['status' => 'completed']);
    }

    /**
     * Host-specific: Update upcoming → ongoing
     */
    private function updateHostBookingsToOngoing($listingIds, Carbon $now): void
    {
        $bookings = Booking::with('listing')
            ->whereIn('listing_id', $listingIds)
            ->where('status', 'upcoming')
            ->whereDate('date_start', '<=', $now->toDateString())
            ->get();

        foreach ($bookings as $booking) {
            if ($this->shouldBeOngoing($booking, $now)) {
                $booking->update(['status' => 'ongoing']);
            }
        }
    }

    /**
     * Host-specific: Update ongoing → completed
     */
    private function updateHostBookingsToCompleted($listingIds, Carbon $now): void
    {
        $bookings = Booking::with('listing')
            ->whereIn('listing_id', $listingIds)
            ->where('status', 'ongoing')
            ->whereDate('date_end', '<=', $now->toDateString())
            ->get();

        foreach ($bookings as $booking) {
            if ($this->shouldBeCompleted($booking, $now)) {
                $booking->update(['status' => 'completed']);
            }
        }
    }

    /**
     * Host-specific: Handle missed bookings
     */
    private function updateHostMissedBookings($listingIds, Carbon $now): void
    {
        Booking::whereIn('listing_id', $listingIds)
            ->where('status', 'upcoming')
            ->whereDate('date_end', '<', $now->toDateString())
            ->update(['status' => 'completed']);
    }

    /**
     * Check if booking should transition to "ongoing"
     * Based on: date_start + check_in_time <= now
     */
    private function shouldBeOngoing(Booking $booking, Carbon $now): bool
    {
        if (!$booking->listing) {
            // Fallback to date-only check if no listing
            return $booking->date_start->startOfDay()->lte($now);
        }

        $checkInTime = $booking->listing->check_in_time;
        $checkInDateTime = $booking->date_start->copy()
            ->setTime($checkInTime->hour, $checkInTime->minute, 0);

        return $checkInDateTime->lte($now);
    }

    /**
     * Check if booking should transition to "completed"
     * Based on: date_end + check_out_time <= now
     */
    private function shouldBeCompleted(Booking $booking, Carbon $now): bool
    {
        if (!$booking->listing) {
            // Fallback to date-only check if no listing
            return $booking->date_end->endOfDay()->lt($now);
        }

        $checkOutTime = $booking->listing->check_out_time;
        $checkOutDateTime = $booking->date_end->copy()
            ->setTime($checkOutTime->hour, $checkOutTime->minute, 0);

        return $checkOutDateTime->lte($now);
    }
}
