<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateFeedbackRequest;
use App\Models\AppFeedback;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AppFeedbackController extends Controller
{
    /**
     * Get user's feedbacks
     *
     * Returns all feedbacks submitted by the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserFeedbacks()
    {
        $userId = auth('sanctum')->id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $feedbacks = AppFeedback::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Feedbacks retrieved successfully',
            'feedbacks' => $feedbacks,
        ]);
    }

    /**
     * Check if user can submit feedback
     *
     * Checks if the user has submitted feedback within the last hour.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function canSubmitFeedback()
    {
        $userId = auth('sanctum')->id();

        if (!$userId) {
            return response()->json([
                'can_submit' => true,
                'next_submission_time' => null,
            ]);
        }

        $lastFeedback = AppFeedback::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastFeedback) {
            return response()->json([
                'can_submit' => true,
                'next_submission_time' => null,
            ]);
        }

        $nextSubmissionTime = Carbon::parse($lastFeedback->created_at)->addHour();
        $canSubmit = Carbon::now()->gte($nextSubmissionTime);

        return response()->json([
            'can_submit' => $canSubmit,
            'next_submission_time' => $canSubmit ? null : $nextSubmissionTime->toIso8601String(),
            'last_feedback' => $lastFeedback,
        ]);
    }

    /**
     * Create App Feedback
     *
     * Creates a new feedback entry. Requires authentication.
     * Users can only submit feedback once per hour.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAppFeedback(CreateFeedbackRequest $request)
    {
        $userId = auth('sanctum')->id();

        if (!$userId) {
            return response()->json([
                'message' => 'You must be logged in to submit feedback',
            ], 401);
        }

        // Check if the user submitted feedback within the last hour
        $lastFeedback = AppFeedback::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->first();

        if ($lastFeedback) {
            $nextSubmissionTime = Carbon::parse($lastFeedback->created_at)->addHour();
            return response()->json([
                'message' => 'You can only submit feedback once per hour',
                'next_submission_time' => $nextSubmissionTime->toIso8601String(),
                'feedback' => $lastFeedback,
            ], 429);
        }

        // Handle media uploads
        $mediaFiles = [];
        if ($request->hasFile('media')) {
            $files = $request->file('media');
            // Handle both single and multiple files
            if (!is_array($files)) {
                $files = [$files];
            }
            
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('feedback', 'public');
                    $mediaFiles[] = [
                        'path' => $path,
                        'url' => Storage::url($path),
                        'type' => str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image',
                        'mime_type' => $file->getMimeType(),
                    ];
                }
            }
        }

        // Create feedback
        $feedback = AppFeedback::create([
            'user_id' => $userId,
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
            'media' => !empty($mediaFiles) ? $mediaFiles : null,
        ]);

        return response()->json([
            'message' => 'Feedback submitted successfully',
            'feedback' => $feedback,
        ]);
    }

    /**
     * Update App Feedback
     *
     * Updates an existing feedback entry. Users can only update feedbacks
     * that were created on the same day.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAppFeedback(CreateFeedbackRequest $request, $id)
    {
        $userId = auth('sanctum')->id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $feedback = AppFeedback::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$feedback) {
            return response()->json([
                'message' => 'Feedback not found',
            ], 404);
        }

        // Check if feedback was created today
        if (!Carbon::parse($feedback->created_at)->isToday()) {
            return response()->json([
                'message' => 'You can only edit feedback submitted today',
            ], 403);
        }

        // Handle media removal
        $mediaFiles = $feedback->media ?? [];
        $removeMediaPaths = $request->input('remove_media', []);
        
        if (!empty($removeMediaPaths)) {
            // Convert to array if string
            if (is_string($removeMediaPaths)) {
                $removeMediaPaths = json_decode($removeMediaPaths, true) ?? [$removeMediaPaths];
            }
            
            // Remove specified media files
            foreach ($removeMediaPaths as $pathToRemove) {
                // Find and remove the media item
                $mediaFiles = array_filter($mediaFiles, function ($media) use ($pathToRemove) {
                    if ($media['path'] === $pathToRemove) {
                        // Delete the file from storage
                        Storage::disk('public')->delete($pathToRemove);
                        return false;
                    }
                    return true;
                });
            }
            // Re-index array
            $mediaFiles = array_values($mediaFiles);
        }

        // Handle new media uploads
        if ($request->hasFile('media')) {
            $files = $request->file('media');
            // Handle both single and multiple files
            if (!is_array($files)) {
                $files = [$files];
            }
            
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('feedback', 'public');
                    $mediaFiles[] = [
                        'path' => $path,
                        'url' => Storage::url($path),
                        'type' => str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image',
                        'mime_type' => $file->getMimeType(),
                    ];
                }
            }
        }

        $feedback->update([
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
            'media' => !empty($mediaFiles) ? $mediaFiles : null,
        ]);

        return response()->json([
            'message' => 'Feedback updated successfully',
            'feedback' => $feedback->fresh(),
        ]);
    }

    /**
     * Delete App Feedback
     *
     * Deletes a feedback entry. Users can only delete feedbacks
     * that were created on the same day.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAppFeedback($id)
    {
        $userId = auth('sanctum')->id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $feedback = AppFeedback::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$feedback) {
            return response()->json([
                'message' => 'Feedback not found',
            ], 404);
        }

        // Check if feedback was created today
        if (!Carbon::parse($feedback->created_at)->isToday()) {
            return response()->json([
                'message' => 'You can only delete feedback submitted today',
            ], 403);
        }

        $feedback->delete();

        return response()->json([
            'message' => 'Feedback deleted successfully',
        ]);
    }
}
