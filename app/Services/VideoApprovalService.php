<?php

namespace App\Services;

use App\Models\Video;

class VideoApprovalService
{
    public function approveVideo(string $videoId): bool
    {
        $video = Video::findOrFail($videoId);

        return $video->update(['is_approved' => true]);
    }
}
