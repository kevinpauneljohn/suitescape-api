<?php

namespace App\Services;

use App\Models\Video;
use Exception;
use Micilini\VideoStream\VideoStream;

class VideoRetrievalService
{
    public function getVideoUrl(Video $video)
    {
        return public_path('storage/videos/' . $video['filename']);
    }

    public function streamVideo(Video $video)
    {
//        $videoUrl = storage_path('app/public/videos/' . $video['filename']);
        $videoUrl = $this->getVideoUrl($video);
        $options = [
            'is_localPath' => true
        ];

        try {
            return (new VideoStream())->streamVideo($videoUrl, $options);
        } catch (Exception $exception) {
            return response()->json(["error" => $exception->getMessage(),], 404);
        }
    }
}
