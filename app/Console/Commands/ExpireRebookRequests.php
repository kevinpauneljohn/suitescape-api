<?php

namespace App\Console\Commands;

use App\Models\RebookRequest;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ExpireRebookRequests extends Command
{
    protected $signature   = 'rebook:expire';
    protected $description = 'Mark overdue pending rebook requests as expired and notify the guest.';

    public function __construct(private NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = RebookRequest::with(['booking.listing', 'booking.user'])
            ->expired()   // pending + expires_at <= now()
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired rebook requests found.');
            return self::SUCCESS;
        }

        foreach ($expired as $req) {
            $req->update(['status' => 'expired']);

            $booking = $req->booking;
            if (! $booking) continue;

            // Notify the guest
            $this->notificationService->createNotification([
                'user_id'   => $booking->user_id,
                'title'     => 'Date-Change Request Expired',
                'message'   => "Your date-change request for \"{$booking->listing->name}\" expired before the host responded. You can submit a new request.",
                'type'      => 'rebook_expired',
                'action_id' => $booking->id,
            ]);

            $this->line("Expired request {$req->id} (booking {$booking->id})");
        }

        $this->info("Marked {$expired->count()} request(s) as expired.");
        return self::SUCCESS;
    }
}
