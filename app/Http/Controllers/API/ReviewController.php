<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\GuestReview;
use App\Models\Review;
use App\Services\ReviewCreateService;
use App\Services\ReviewRetrievalService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private ReviewRetrievalService $reviewRetrievalService;
    private ReviewCreateService $reviewCreateService;

    public function __construct(ReviewRetrievalService $reviewRetrievalService, ReviewCreateService $reviewCreateService)
    {
        $this->middleware('auth:sanctum');
        $this->reviewRetrievalService = $reviewRetrievalService;
        $this->reviewCreateService = $reviewCreateService;
    }

    public function getAllReviews()
    {
        return ReviewResource::collection($this->reviewRetrievalService->getAllReviews());
    }

    public function getReview(string $id)
    {
        return new ReviewResource($this->reviewRetrievalService->getReview($id));
    }

    public function createReview(CreateReviewRequest $request)
    {
        $validated = $request->validated();

        $serviceRatings = collect($validated)->except([
            'listing_id',
            'booking_id',
            'feedback',
            'overall_rating',
        ])->all();

        $this->reviewCreateService->createReview(
            $validated['listing_id'],
            $validated['feedback'],
            $validated['overall_rating'],
            $serviceRatings,
            $validated['booking_id'] ?? null,
        );

        return response()->json(['message' => 'Review added successfully']);
    }

    /**
     * Host rates a guest for a completed booking.
     * POST /reviews/rate-guest
     */
    public function rateGuest(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'uuid', 'exists:bookings,id'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'content'    => ['nullable', 'string', 'max:1000'],
        ]);

        $guestReview = $this->reviewCreateService->createGuestReview(
            $validated['booking_id'],
            $validated['rating'],
            $validated['content'] ?? null,
        );

        return response()->json([
            'message'      => 'Guest review submitted successfully',
            'guest_review' => [
                'id'         => $guestReview->id,
                'booking_id' => $guestReview->booking_id,
                'guest_id'   => $guestReview->guest_id,
                'rating'     => $guestReview->rating,
                'content'    => $guestReview->content,
                'created_at' => $guestReview->created_at,
            ],
        ]);
    }

    /**
     * Get all reviews for the authenticated host's listings.
     * GET /reviews/host-reviews
     */
    public function getMyListingReviews(Request $request)
    {
        $hostId = auth()->id();

        $reviews = Review::with(['user', 'listing', 'booking.guestReview'])
            ->whereHas('listing', fn ($q) => $q->where('user_id', $hostId))
            ->latest()
            ->get()
            ->map(fn ($review) => [
                'id'          => $review->id,
                'listing_id'  => $review->listing_id,
                'listing_name'=> $review->listing->name ?? null,
                'booking_id'  => $review->booking_id,
                'guest'       => [
                    'id'            => $review->user->id ?? null,
                    'name'          => trim(($review->user->firstname ?? '') . ' ' . ($review->user->lastname ?? '')),
                    'profile_image' => $review->user->profile_image_url ?? null,
                ],
                'rating'      => $review->rating,
                'content'     => $review->content,
                'reviewed_at' => $review->reviewed_at ?? $review->created_at,
                // Has the host already replied to this guest?
                'guest_review' => $review->booking?->guestReview ? [
                    'id'      => $review->booking->guestReview->id,
                    'rating'  => $review->booking->guestReview->rating,
                    'content' => $review->booking->guestReview->content,
                ] : null,
            ]);

        return response()->json(['data' => $reviews]);
    }

    /**
     * Get the host's review of a guest for a specific booking.
     * GET /reviews/guest-review/{bookingId}
     */
    public function getGuestReview(string $bookingId)
    {
        $guestReview = GuestReview::where('booking_id', $bookingId)
            ->with('host')
            ->first();

        if (!$guestReview) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id'         => $guestReview->id,
                'booking_id' => $guestReview->booking_id,
                'rating'     => $guestReview->rating,
                'content'    => $guestReview->content,
                'host'       => [
                    'id'            => $guestReview->host->id ?? null,
                    'name'          => trim(($guestReview->host->firstname ?? '') . ' ' . ($guestReview->host->lastname ?? '')),
                    'profile_image' => $guestReview->host->profile_image_url ?? null,
                ],
                'created_at' => $guestReview->created_at,
            ],
        ]);
    }
}
