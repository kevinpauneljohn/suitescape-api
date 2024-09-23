<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateFeedbackRequest;
use App\Models\AppFeedback;

class AppFeedbackController extends Controller
{
    public function createAppFeedback(CreateFeedbackRequest $request)
    {
        $userId = auth('sanctum')->id();

        // Check if there is an AppFeedback with the same user_id
        if ($userId) {
            $existingFeedback = AppFeedback::where('user_id', $userId)->first();

            if ($existingFeedback) {
                return response()->json([
                    'message' => 'Feedback already exists for this user',
                    'feedback' => $existingFeedback,
                ], 409);
            }
        }

        // Create a new feedback for the user
        $feedback = AppFeedback::create(array_merge($request->validated(), [
            'user_id' => $userId,
        ]));

        return response()->json([
            'message' => 'Feedback created successfully',
            'feedback' => $feedback,
        ]);
    }
}
