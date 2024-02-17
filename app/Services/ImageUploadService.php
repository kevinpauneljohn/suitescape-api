<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageUploadService
{
    protected FileNameService $fileNameService;

    public function __construct(FileNameService $fileNameService)
    {
        $this->fileNameService = $fileNameService;
    }

    public function upload(UploadedFile $image): string
    {
        $filename = $this->fileNameService->generateFileName($image->extension());
        $image->storeAs('images', $filename, 'public');

        return $filename;
    }
}
