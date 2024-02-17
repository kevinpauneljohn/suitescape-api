<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class VideoUploadService
{
    protected FilenameService $filenameService;

    public function __construct(FilenameService $filenameService)
    {
        $this->filenameService = $filenameService;
    }

    public function upload(UploadedFile $video): string
    {
        $filename = $this->filenameService->generateFileName($video->extension());
        $video->storeAs('videos', $filename, 'public');

        return $filename;
    }
}
