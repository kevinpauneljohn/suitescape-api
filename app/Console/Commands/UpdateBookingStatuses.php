<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateBookingStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update booking statuses based on dates (upcoming -> ongoing -> completed)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        $updatedCount = 0;

        // 1. Update "upcoming" bookings to "ongoing" if check-in date has arrived
        $upcomingToOngoing = Booking::where('status', 'upcoming')
            ->whereDate('date_start', '<=', $today)
            ->whereDate('date_end', '>=', $today)
            ->get();

        foreach ($upcomingToOngoing as $booking) {
            $booking->update(['status' => 'ongoing']);
            $updatedCount++;
            $this->info("Booking {$booking->id}: upcoming -> ongoing");
            Log::info("Booking status auto-updated", [
                'booking_id' => $booking->id,
                'old_status' => 'upcoming',
                'new_status' => 'ongoing',
                'date_start' => $booking->date_start,
                'date_end' => $booking->date_end,
            ]);
        }

        // 2. Update "ongoing" bookings to "completed" if check-out date has passed
        $ongoingToCompleted = Booking::where('status', 'ongoing')
            ->whereDate('date_end', '<', $today)
            ->get();

        foreach ($ongoingToCompleted as $booking) {
            $booking->update(['status' => 'completed']);
            $updatedCount++;
            $this->info("Booking {$booking->id}: ongoing -> completed");
            Log::info("Booking status auto-updated", [
                'booking_id' => $booking->id,
                'old_status' => 'ongoing',
                'new_status' => 'completed',
                'date_start' => $booking->date_start,
                'date_end' => $booking->date_end,
            ]);
        }

        // 3. Update "upcoming" bookings directly to "completed" if they were missed entirely
        // (check-out date has passed but status is still upcoming)
        $upcomingToCompleted = Booking::where('status', 'upcoming')
            ->whereDate('date_end', '<', $today)
            ->get();

        foreach ($upcomingToCompleted as $booking) {
            $booking->update(['status' => 'completed']);
            $updatedCount++;
            $this->info("Booking {$booking->id}: upcoming -> completed (missed)");
            Log::info("Booking status auto-updated (missed booking)", [
                'booking_id' => $booking->id,
                'old_status' => 'upcoming',
                'new_status' => 'completed',
                'date_start' => $booking->date_start,
                'date_end' => $booking->date_end,
            ]);
        }

        $this->info("Updated {$updatedCount} booking(s) successfully.");

        return Command::SUCCESS;
    }
}
