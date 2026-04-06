<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendUpcomingBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-upcoming-booking-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email reminders to guests and hosts for bookings starting tomorrow';

    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        parent::__construct();
        $this->mailService = $mailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        // Find all upcoming bookings that start tomorrow and haven't been reminded yet
        $bookings = Booking::with(['user', 'listing.user', 'bookingRooms.room.roomCategory'])
            ->where('status', 'upcoming')
            ->whereDate('date_start', $tomorrow)
            ->whereNull('upcoming_reminder_sent_at')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No upcoming bookings to remind for ' . $tomorrow);
            return;
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($bookings as $booking) {
            try {
                $this->mailService->sendUpcomingBookingReminders($booking);

                // Mark as reminded so we don't send again
                $booking->update(['upcoming_reminder_sent_at' => now()]);

                $sentCount++;
                $this->line("✅ Reminder sent for booking {$booking->id} — Guest: {$booking->user->full_name}, Listing: {$booking->listing->name}");
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("❌ Failed to send reminder for booking {$booking->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done! Sent: {$sentCount}, Failed: {$failedCount}, Total: {$bookings->count()}");
    }
}
