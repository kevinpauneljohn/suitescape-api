<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\ListingViewService;
use App\Services\VideoRetrievalService;
use Spatie\Permission\Exceptions\UnauthorizedException;

class VideoController extends Controller
{
    private VideoRetrievalService $videoRetrievalService;

    public function __construct(VideoRetrievalService $videoRetrievalService)
    {
        $this->videoRetrievalService = $videoRetrievalService;
    }

    public function getAllVideos()
    {
        return VideoResource::collection($this->videoRetrievalService->getAllVideos());
    }

    public function getVideoFeed()
    {
        return VideoResource::collection($this->videoRetrievalService->getVideoFeed());
    }

    public function getVideo(string $id)
    {
        $video = Video::findOrFail($id);
        $listing = $video->listing;

        $user = auth('sanctum')->user();
        if ($video->privacy === 'private' && (! $user || ! $video->isOwnedBy($user))) {
            throw new UnauthorizedException(403, 'You do not have permission to view this video.');
        }

        (new ListingViewService($listing, $user))->addView();

        return $this->videoRetrievalService->streamVideo($video);
    }
}
