<?php

namespace App\Services;

use App\Models\Review;

class ReviewRetrievalService
{
    public function getAllReviews()
    {
        return Review::all();
    }

    public function getReview(string $id)
    {
        return Review::findOrFail($id);
    }
}
