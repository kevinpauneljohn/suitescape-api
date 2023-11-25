<?php

namespace App\Services;

use App\Models\Video;

class VideoFeedService
{
    public function getAllPublicVideos()
    {
        return Video::public()
            ->desc()
            ->with('listing')
            ->cursorPaginate(5);
    }
}
