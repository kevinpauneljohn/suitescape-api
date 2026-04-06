<?php

namespace App\Mail;

use App\Models\RebookRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RebookPaymentConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public RebookRequest $rebookRequest;

    public function __construct(RebookRequest $rebookRequest)
    {
        $this->rebookRequest = $rebookRequest->load([
            'booking.listing.user',
            'booking.user',
            'requester',
        ]);
    }

    public function envelope(): Envelope
    {
        $listing = $this->rebookRequest->booking->listing->name ?? 'your listing';

        return new Envelope(
            subject: "Your booking dates for \"{$listing}\" have been updated",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.rebook.payment_confirmed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
