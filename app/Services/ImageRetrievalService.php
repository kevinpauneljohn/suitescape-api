<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;

class ImageRetrievalService
{
    public function getAllImages()
    {
        return Image::all();
    }

    public function getStoragePath(string $filename)
    {
        //        return public_path('storage/images/'.$filename);
        return storage_path('app/public/images/'.$filename);
    }

    public function getStorageUrl(string $filename)
    {
        return Storage::url('images/'.$filename);
    }
}
