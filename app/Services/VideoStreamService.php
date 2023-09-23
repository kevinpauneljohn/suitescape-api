<?php

namespace App\Services;

use App\Models\Video;
use Exception;
use Micilini\VideoStream\VideoStream;

class VideoStreamService
{
    public function stream(Video $video)
    {
        $localUrl = public_path('storage/videos/' . $video['filename']);
//        $localUrl = storage_path('app/videos/' . $video['filename']);
        $options = [
            'is_localPath' => true
        ];

        try {
            return (new VideoStream())->streamVideo($localUrl, $options);
        } catch (Exception $exception) {
            return response()->json([
                "message" => $exception->getMessage(),
            ], 404);
        }
    }
}
