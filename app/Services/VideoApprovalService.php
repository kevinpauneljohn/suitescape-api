<?php

namespace App\Services;

use App\Models\Video;

class VideoApprovalService
{
    public function approveVideo(Video $video): bool
    {
        return $video->update(['is_approved' => true]);
    }
}
