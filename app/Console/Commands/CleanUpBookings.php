<?php

namespace App\Console\Commands;

use App\Services\BookingDeleteService;
use Illuminate\Console\Command;

class CleanUpBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-up-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes old cancelled bookings and removes unavailable dates related to completed bookings';

    protected BookingDeleteService $bookingDeleteService;

    public function __construct(BookingDeleteService $bookingDeleteService)
    {
        parent::__construct();

        $this->bookingDeleteService = $bookingDeleteService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->bookingDeleteService->cleanOldCancelledBookings();
        $this->bookingDeleteService->cleanUnavailableDates();
        $this->info('Old cancelled bookings deleted and unavailable dates removed successfully');
    }
}
