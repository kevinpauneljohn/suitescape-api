<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageUploadService
{
    public function generateFileName(): string
    {
        return date('d-m-Y-H-i-s').'_'.auth('sanctum')->user()->email.'_'.uniqid();
    }

    public function upload(UploadedFile $video): string
    {
        $filename = $this->generateFileName().$video->getClientOriginalExtension();
        $video->storeAs('images', $filename, 'public');

        return $filename;
    }
}
