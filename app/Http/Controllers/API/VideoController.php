<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterVideoRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\VideoApprovalService;
use App\Services\VideoRetrievalService;
use App\Services\VideoUploadService;
use Exception;
use Spatie\Permission\Exceptions\UnauthorizedException;

class VideoController extends Controller
{
    private VideoRetrievalService $videoRetrievalService;

    private VideoUploadService $videoUploadService;

    private VideoApprovalService $videoApprovalService;

    public function __construct(VideoRetrievalService $videoRetrievalService, VideoUploadService $videoUploadService, VideoApprovalService $videoApprovalService)
    {
        $this->middleware('auth:sanctum')->only(['uploadVideo']);

        $this->videoRetrievalService = $videoRetrievalService;
        $this->videoUploadService = $videoUploadService;
        $this->videoApprovalService = $videoApprovalService;
    }

    /**
     * Get All Videos
     *
     * Retrieves a collection of all videos available in the system.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllVideos()
    {
        return VideoResource::collection($this->videoRetrievalService->getAllVideos());
    }

    /**
     * Get Video Feed
     *
     * Retrieves a filtered collection of videos based on the provided criteria.
     * This can include filtering by category, tags, or any other specified attributes.
     *
     * @param FilterVideoRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getVideoFeed(FilterVideoRequest $request)
    {
        $validated = $request->validated();

        return VideoResource::collection($this->videoRetrievalService->getVideoFeed($validated))
            ->additional(['order' => empty($validated) ? 'default' : 'filtered']);
    }

    /**
     * Get Video
     *
     * Retrieves a single video by its ID. This method also checks for the video's privacy settings
     * and determines if the current user has permission to view the video.
     *
     * @param string $id
     * @return VideoResource
     */
    public function getVideo(string $id)
    {
        $video = Video::findOrFail($id);

        $user = auth('sanctum')->user();
        if ($video->privacy === 'private' && (! $user || ! $video->isOwnedBy($user))) {
            throw new UnauthorizedException(403, 'You do not have permission to view this video.');
        }

        //        return $this->videoRetrievalService->streamVideo($video);
        return new VideoResource($video);
    }

    /**
     * Upload Video
     *
     * Handles the uploading of a new video to the system. This method processes the uploaded video file,
     * stores it, and returns the filename of the stored video.
     *
     * @param UploadVideoRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadVideo(UploadVideoRequest $request)
    {
        $filename = $this->videoUploadService->upload($request->file('video'));

        return response()->json([
            'message' => 'Video uploaded successfully',
            'filename' => $filename,
        ]);
    }

    /**
     * Approve Video
     *
     * Marks a video as approved. This is typically used in a moderation workflow where videos
     * need to be reviewed before they are made available to the public.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function approveVideo(string $id)
    {
        $result = $this->videoApprovalService->approveVideo($id);

        if (! $result) {
            throw new Exception('Failed to approve video.');
        }

        return response()->json([
            'approved' => true,
            'message' => 'Video approved successfully',
        ]);
    }
}
