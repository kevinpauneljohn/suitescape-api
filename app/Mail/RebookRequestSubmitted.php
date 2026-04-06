<?php

namespace App\Mail;

use App\Models\RebookRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RebookRequestSubmitted extends Mailable implements ShouldQueue
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
        $guest   = $this->rebookRequest->requester->full_name ?? 'A guest';

        return new Envelope(
            subject: "{$guest} requested to change booking dates for \"{$listing}\"",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.rebook.submitted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
