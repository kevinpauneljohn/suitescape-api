<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadVideoRequest;
use App\Models\Video;
use App\Services\ListingService;
use App\Services\VideoRetrievalService;
use App\Services\VideoStreamService;
use App\Services\VideoUploadService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    private VideoUploadService $videoUploadService;
    private VideoStreamService $videoStreamService;
    private VideoRetrievalService $videoRetrievalService;
    private ListingService $listingService;

    public function __construct(VideoUploadService $videoUploadService, VideoStreamService $videoStreamService, VideoRetrievalService $videoRetrievalService, ListingService $listingService)
    {
        $this->videoUploadService = $videoUploadService;
        $this->videoStreamService = $videoStreamService;
        $this->videoRetrievalService = $videoRetrievalService;
        $this->listingService = $listingService;

        $this->middleware('auth:sanctum')->only('uploadVideo');
    }

    public function uploadVideo(UploadVideoRequest $request)
    {
        $validated = $request->validated();

        $filename = $this->videoUploadService->upload($validated['file']);
        $validated['filename'] = $filename;

        $listing = $this->listingService->createListingAndRoomCategory(auth()->user());
        $video = $listing->videos()->create($validated);

        return response()->json([
            "message" => "Video successfully uploaded.",
            "video" => $video,
        ]);
    }

    public function getVideo(string $id)
    {
        try {
            $video = Video::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "message" => "Video not found."
            ], 404);
        }

        $user = auth('sanctum')->user();

        if ($video->privacy === 'private' && !$video->isOwner($user)) {
            return response()->json([
                "message" => "You are not authorized to view this video."
            ], 403);
        }

        return $this->videoStreamService->stream($video);
    }

    public function getAllVideos()
    {
        return $this->videoRetrievalService->getAllPublicVideos();
    }
}
