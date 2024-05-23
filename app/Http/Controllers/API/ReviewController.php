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
