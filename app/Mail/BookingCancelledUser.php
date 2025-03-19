<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledUser extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    public float $suitescapeCancellationFee;

    public float $cancellationFee;

    public string $cancellationPolicy;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking, float $suitescapeCancellationFee, float $cancellationFee, string $cancellationPolicy)
    {
        $this->booking = $booking->load(['user', 'listing.user', 'bookingRooms.room.roomCategory']);
        $this->suitescapeCancellationFee = $suitescapeCancellationFee;
        $this->cancellationFee = $cancellationFee;
        $this->cancellationPolicy = $cancellationPolicy;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Cancelled User',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.cancelled.user',
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
