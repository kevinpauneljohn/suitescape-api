<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageUploadService
{
    public function generateFileName(): string
    {
        return date('d-m-Y-H-i-s').'_'.auth('sanctum')->user()->email.'_'.uniqid();
    }

    public function upload(UploadedFile $image): string
    {
        $filename = $this->generateFileName().'.'.$image->getClientOriginalExtension();
        $image->storeAs('images', $filename, 'public');

        return $filename;
    }
}
