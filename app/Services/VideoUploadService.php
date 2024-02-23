<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class VideoUploadService
{
    protected FileNameService $fileNameService;

    public function __construct(FileNameService $fileNameService)
    {
        $this->fileNameService = $fileNameService;
    }

    public function upload(UploadedFile $video): string
    {
        $filename = $this->fileNameService->generateFileName($video->extension());
        $video->storeAs('videos', $filename, 'public');

        return $filename;
    }
}
