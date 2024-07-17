<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Services\ReviewCreateService;
use App\Services\ReviewRetrievalService;

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

    /**
     * Get All Reviews
     *
     * Retrieves a collection of all reviews.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllReviews()
    {
        return ReviewResource::collection($this->reviewRetrievalService->getAllReviews());
    }

    /**
     * Get Review
     *
     * Retrieves a specific review by ID.
     *
     * @param string $id
     * @return ReviewResource
     */
    public function getReview(string $id)
    {
        return new ReviewResource($this->reviewRetrievalService->getReview($id));
    }

    /**
     * Create Review
     *
     * Creates a new review based on the provided information.
     * Validates the incoming request data and creates a review.
     * Returns a JSON response indicating the success of the review creation process.
     *
     * @param CreateReviewRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createReview(CreateReviewRequest $request)
    {
        $validated = $request->validated();

        $serviceRatings = collect($validated)->except([
            'listing_id',
            'feedback',
            'overall_rating',
        ])->all();

        $this->reviewCreateService->createReview(
            $validated['listing_id'],
            $validated['feedback'],
            $validated['overall_rating'],
            $serviceRatings
        );

        return response()->json([
            'message' => 'Review added successfully',
        ]);
    }
}
