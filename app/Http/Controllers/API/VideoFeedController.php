<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\VideoFeedService;
use Illuminate\Http\Request;

class VideoFeedController extends Controller
{
    private VideoFeedService $videoFeedService;

    public function __construct(VideoFeedService $videoFeedService)
    {
        $this->videoFeedService = $videoFeedService;
    }

    public function getVideoFeed()
    {
        return $this->videoFeedService->getAllPublicVideos();
    }
}
