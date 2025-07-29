<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookingDeleteService;

class CancelExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cancel-expired-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes bookings that have expired and are not paid for or incomplete after a certain period';

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
        $this->bookingDeleteService->cancelExpiredBookings();
        $this->info('Expired bookings cancelled successfully');
    }
}
