<?php

namespace App\Console\Commands;

use App\Services\BookingStatusService;
use Illuminate\Console\Command;

class UpdateBookingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-booking-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manages booking statuses based on the current date';

    protected BookingStatusService $bookingStatusService;

    public function __construct(BookingStatusService $bookingStatusService)
    {
        parent::__construct();

        $this->bookingStatusService = $bookingStatusService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->bookingStatusService->updateBookingStatuses();
        $this->info('Booking statuses updated successfully');
    }
}
