<?php

namespace App\Services;

use App\Models\Video;

class VideoRetrievalService
{
    public function getAllPublicVideos()
    {
        return Video::where('privacy', 'public')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->with('listing')
            ->cursorPaginate(5);
    }
}
