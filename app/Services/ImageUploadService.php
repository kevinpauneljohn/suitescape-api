<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageUploadService
{
    protected FilenameService $filenameService;

    public function __construct(FilenameService $filenameService)
    {
        $this->filenameService = $filenameService;
    }

    public function upload(UploadedFile $image): string
    {
        $filename = $this->filenameService->generateFileName($image->extension());
        $image->storeAs('images', $filename, 'public');

        return $filename;
    }
}
