<?php

namespace App\Console\Commands;

use App\Services\BookingHoldService;
use Illuminate\Console\Command;

class CleanupExpiredHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-expired-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired booking holds to release blocked dates';

    protected BookingHoldService $holdService;

    public function __construct(BookingHoldService $holdService)
    {
        parent::__construct();
        $this->holdService = $holdService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = $this->holdService->cleanupExpiredHolds();
        $this->info("Cleaned up {$count} expired hold(s).");
    }
}
