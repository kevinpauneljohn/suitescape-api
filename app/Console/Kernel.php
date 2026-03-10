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
        // Run hourly to catch check-in/check-out times throughout the day
        $schedule->command('app:update-booking-status')->hourly();
        $schedule->command('app:clean-up-bookings')->daily();
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();
        $schedule->command('app:cancel-expired-bookings')->everyFiveMinutes();
        // Shuffle video feed order daily at midnight
        $schedule->command('videos:shuffle-order')->daily();
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
