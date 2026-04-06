<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AutoCompleteUnratedBookings extends Command
{
    protected $signature = 'app:auto-complete-unrated-bookings';
    protected $description = 'Move completed bookings with no review to "completed" (hide from TO RATE) after 2 days';

    public function handle(): void
    {
        // Find all completed bookings where:
        // - status is completed (has_reviewed = false means no review yet → stays in TO RATE)
        // - no review linked via booking_id
        // - checkout was more than 2 days ago
        $cutoff = Carbon::now()->subDays(2);

        // We use the bookings table's updated_at as a proxy for when it became completed.
        // A cleaner alternative would be a completed_at column, but this works for now.
        $bookings = Booking::where('status', 'completed')
            ->where('date_end', '<', $cutoff->toDateString())
            ->whereDoesntHave('review')
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            // Mark as already reviewed (set a placeholder review_at on the booking)
            // We do this by touching the booking so has_reviewed stays false but
            // the frontend filter (status=completed AND has_reviewed=false) will
            // use the new "auto_completed_at" flag we add, OR we just rely on the
            // date_end + 2 days rule directly in the frontend filter.
            // Since we don't want to fake a review, we add a dedicated column:
            // see migration 2026_04_06_add_review_deadline_passed_to_bookings.
            // For now mark with a flag via direct update.
            $booking->timestamps = false;
            $booking->update(['review_deadline_passed' => true]);
            $count++;
        }

        if ($count > 0) {
            Log::info("Auto-completed {$count} unrated booking(s) — review window expired.");
        }

        $this->info("Processed {$count} unrated booking(s).");
    }
}
