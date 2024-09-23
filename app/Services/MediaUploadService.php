<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class MediaUploadService
{
    protected FileNameService $fileNameService;

    public function __construct(FileNameService $fileNameService)
    {
        $this->fileNameService = $fileNameService;
    }

    public function upload(UploadedFile $media, string $directory, bool $temp = false): string|array
    {
        $filename = $this->fileNameService->generateFileName($media->extension());

        // Upload temp media to the storage
        if ($temp) {
            $tempPath = $media->storeAs($directory, 'temp_'.$filename, 'public');

            return [
                'filename' => $filename,
                'temp_path' => $tempPath,
            ];
        }

        $media->storeAs($directory, $filename, 'public');

        return $filename;
    }
}
