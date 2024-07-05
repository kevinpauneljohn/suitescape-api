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

    public function getAllVideos()
    {
        return VideoResource::collection($this->videoRetrievalService->getAllVideos());
    }

    public function getVideoFeed(FilterVideoRequest $request)
    {
        $validated = $request->validated();

        return VideoResource::collection($this->videoRetrievalService->getVideoFeed($validated))
            ->additional(['order' => empty($validated) ? 'default' : 'filtered']);
    }

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

    public function uploadVideo(UploadVideoRequest $request)
    {
        $filename = $this->videoUploadService->upload($request->file('video'));

        return response()->json([
            'message' => 'Video uploaded successfully',
            'filename' => $filename,
        ]);
    }

    /**
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
