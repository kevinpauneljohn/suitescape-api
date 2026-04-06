<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UpcomingBookingGuest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Booking $booking;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking->load(['user', 'listing.user', 'bookingRooms.room.roomCategory']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $listingName = $this->booking->listing->name ?? 'your booking';

        return new Envelope(
            subject: "Reminder: Your stay at \"{$listingName}\" is tomorrow!",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.upcoming.guest',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
