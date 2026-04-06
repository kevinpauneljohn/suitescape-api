<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GuestReview;
use App\Models\Listing;
use Illuminate\Support\Carbon;

class ReviewCreateService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function createReview($listingId, $feedback, $overallRating, $serviceRatings, ?string $bookingId = null): void
    {
        $userId = auth()->id();
        $listing = Listing::findOrFail($listingId);

        $listing->reviews()->create([
            'booking_id'  => $bookingId,
            'user_id'     => $userId,
            'content'     => $feedback,
            'rating'      => $overallRating,
            'reviewed_at' => Carbon::now(),
        ]);

        $ratingsWithUserId = array_merge($serviceRatings, ['user_id' => $userId]);
        $listing->serviceRatings()->create($ratingsWithUserId);

        // Notify the host about the new review
        $hostUserId = $listing->user_id;
        if ($hostUserId) {
            $guest = auth()->user();
            $guestName = trim(($guest->firstname ?? '') . ' ' . ($guest->lastname ?? ''));

            $this->notificationService->createNotification([
                'user_id'   => $hostUserId,
                'title'     => 'New Review on Your Listing',
                'message'   => "{$guestName} left a {$overallRating}-star review on \"{$listing->name}\".",
                'type'      => 'new_review',
                'action_id' => $listing->id,
            ]);
        }
    }

    /**
     * Host rates a guest for a completed booking.
     */
    public function createGuestReview(string $bookingId, int $rating, ?string $content): GuestReview
    {
        $hostId = auth()->id();
        $booking = Booking::with('listing', 'user')->findOrFail($bookingId);

        // Ensure the authenticated user is the host of this booking
        if ($booking->listing->user_id !== $hostId) {
            abort(403, 'You are not the host of this booking.');
        }

        $guestReview = GuestReview::updateOrCreate(
            ['booking_id' => $bookingId, 'host_id' => $hostId],
            [
                'guest_id' => $booking->user_id,
                'rating'   => $rating,
                'content'  => $content,
            ]
        );

        // Notify the guest that the host left them a review
        $host = auth()->user();
        $hostName = trim(($host->firstname ?? '') . ' ' . ($host->lastname ?? ''));
        $listingName = $booking->listing->name;

        $this->notificationService->createNotification([
            'user_id'   => $booking->user_id,
            'title'     => 'Your Host Left You a Review',
            'message'   => "{$hostName} reviewed your stay at \"{$listingName}\".",
            'type'      => 'host_guest_review',
            'action_id' => $bookingId,
        ]);

        return $guestReview;
    }
}
