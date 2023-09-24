<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadVideoRequest;
use App\Models\Video;
use App\Services\ListingCreateService;
use App\Services\ListingLikeService;
use App\Services\ListingSaveService;
use App\Services\ListingViewService;
use App\Services\VideoRetrievalService;
use App\Services\VideoStreamService;
use App\Services\VideoUploadService;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    private VideoUploadService $videoUploadService;
    private VideoStreamService $videoStreamService;
    private VideoRetrievalService $videoRetrievalService;

    public function __construct(VideoUploadService $videoUploadService, VideoStreamService $videoStreamService, VideoRetrievalService $videoRetrievalService)
    {
        $this->videoUploadService = $videoUploadService;
        $this->videoStreamService = $videoStreamService;
        $this->videoRetrievalService = $videoRetrievalService;

        $this->middleware('auth:sanctum')->only(['uploadVideo', 'likeVideo', 'saveVideo']);
    }

    public function getAllVideos()
    {
        return $this->videoRetrievalService->getAllPublicVideos();
    }

    public function streamVideo(string $id)
    {
        $video = Video::findOrFail($id);
        $listing = $video->listing;
        $user = auth('sanctum')->user();

        if ($video->privacy === 'private' && (!$user || !$video->isOwnedBy($user))) {
            return response()->json([
                "message" => "You are not authorized to view this video."
            ], 403);
        }

        (new ListingViewService($listing, $user))->addView();

        return $this->videoStreamService->stream($video);
    }

    public function uploadVideo(UploadVideoRequest $request)
    {
        $validated = $request->validated();
        $filename = $this->videoUploadService->upload($validated['file']);

        $video = (new ListingCreateService($filename, $validated))->createListingVideo();

        return response()->json([
            "message" => "Video successfully uploaded.",
            "video" => $video,
        ]);
    }

    public function likeVideo(string $id)
    {
        $video = Video::findOrFail($id);
        $listing = $video->listing;
        $listingLikeService = new ListingLikeService($listing);

        $user = auth()->user();

        if ($listing->isLikedBy($user)) {
            $listingLikeService->removeLike();
            $video->listing()->decrement('likes');
            return response()->json([
                "liked" => false,
                "message" => "Listing unliked."
            ]);
        }

        $listingLikeService->addLike();
        $video->listing()->increment('likes');
        return response()->json([
            "liked" => true,
            "message" => "Listing liked."
        ]);
    }

    public function saveVideo(string $id)
    {
        $video = Video::findOrFail($id);
        $listing = $video->listing;
        $listingSaveService = new ListingSaveService($listing);

        $user = auth()->user();

        if ($listing->isSavedBy($user)) {
            $listingSaveService->removeSave();
            return response()->json([
                "saved" => false,
                "message" => "Listing unsaved."
            ]);
        }

        $listingSaveService->addSave();
        return response()->json([
            "saved" => true,
            "message" => "Listing saved."
        ]);
    }
}
