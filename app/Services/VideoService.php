<?php

namespace App\Services;

use App\Models\Video;
use Exception;
use Illuminate\Http\UploadedFile;
use Micilini\VideoStream\VideoStream;

class VideoService
{
    public function getFileName(): string
    {
        return date("d-m-Y-H-i-s") . "_" . auth()->user()->email . "_" . uniqid();
    }

    public function uploadVideo(UploadedFile $video): string
    {
        $filename = $this->getFileName() . $video->getClientOriginalExtension();
        $video->storeAs("videos", $filename, 'public');
        return $filename;
    }

    public function streamVideo(Video $video)
    {
//        $localUrl = storage_path('app/public/videos/' . $video['filename']);
        $localUrl = public_path('storage/videos/' . $video['filename']);
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
