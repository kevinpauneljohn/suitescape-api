<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadVideoRequest;
use App\Models\Listing;
use App\Models\RoomCategory;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only('uploadVideo');
    }

    public function uploadVideo(UploadVideoRequest $request)
    {
        $validated = $request->validated();

        $validated['filename'] = (new VideoService())->uploadVideo($validated['file']);

        // Temporary solution for now so that video can be uploaded
        $listing = auth()->user()->listings()->save(
            Listing::factory()->make()
        );
        $listing->roomCategories()->save(
            RoomCategory::factory()->make()
        );

        $video = $listing->videos()->create($validated);

        return response()->json([
            "message" => "Video successfully uploaded.",
            "video" => $video,
        ]);
    }

    public function getVideo(string $id)
    {
        $video = Video::findOrFail($id);
        $user = auth('sanctum')->user();

        if ($video->privacy === 'private' && $user?->id !== $video->user_id) {
            return response()->json([
                "message" => "You are not authorized to view this video."
            ], 403);
        }

        return (new VideoService())->streamVideo($video);
    }

    public function getAllVideos()
    {
        return Video::where('privacy', 'public')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->with('listing', 'listing.roomCategories')
            ->cursorPaginate(5);
    }
}
