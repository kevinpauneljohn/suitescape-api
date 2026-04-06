<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        $schedule->command('queue:work --stop-when-empty')->withoutOverlapping();
        // Run every 5 minutes to catch check-in/check-out times promptly
        $schedule->command('app:update-booking-status')->everyFiveMinutes();
        $schedule->command('app:clean-up-bookings')->daily();
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();
        $schedule->command('app:cancel-expired-bookings')->everyFiveMinutes();
        $schedule->command('app:cleanup-expired-holds')->everyMinute();
        // Send upcoming booking reminders daily at 8 AM
        $schedule->command('app:send-upcoming-booking-reminders')->dailyAt('08:00');
        // Shuffle video feed order daily at midnight
        $schedule->command('videos:shuffle-order')->daily();
        // Expire pending rebook requests that the host hasn't responded to within 12 hours
        $schedule->command('rebook:expire')->everyFifteenMinutes();
        // Mark completed bookings with no review older than 2 days as review_deadline_passed
        $schedule->command('app:auto-complete-unrated-bookings')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
