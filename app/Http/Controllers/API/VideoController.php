<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\VideoRetrievalService;
use App\Services\VideoUploadService;
use Spatie\Permission\Exceptions\UnauthorizedException;

class VideoController extends Controller
{
    private VideoRetrievalService $videoRetrievalService;

    private VideoUploadService $videoUploadService;

    public function __construct(VideoRetrievalService $videoRetrievalService, VideoUploadService $videoUploadService)
    {
        $this->middleware('auth:sanctum')->only(['uploadVideo']);

        $this->videoRetrievalService = $videoRetrievalService;
        $this->videoUploadService = $videoUploadService;
    }

    public function getAllVideos()
    {
        return VideoResource::collection($this->videoRetrievalService->getAllVideos());
    }

    public function getVideoFeed(FilterRequest $request)
    {
        return VideoResource::collection($this->videoRetrievalService->getVideoFeed($request->validated()));
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
        return $this->videoUploadService->upload($request->file('video'));
    }
}
