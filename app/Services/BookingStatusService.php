<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;

class BookingStatusService
{
    public function updateBookingStatuses(): void
    {
        $today = Carbon::today();
        $this->updateToOngoing($today);
        $this->updateToCompleted($today);
    }

    private function updateToOngoing($today): void
    {
        Booking::where('status', 'upcoming')
            ->whereDate('date_start', '<=', $today)
            ->whereDate('date_end', '>=', $today)
            ->update(['status' => 'ongoing']);
    }

    private function updateToCompleted($today): void
    {
        Booking::where('status', 'ongoing')
            ->whereDate('date_end', '<', $today)
            ->update(['status' => 'completed']);
    }
}
