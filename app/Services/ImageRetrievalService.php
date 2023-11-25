<?php

namespace App\Services;

use App\Models\Image;

class ImageRetrievalService
{
    public function getImageUrl(Image $image)
    {
        return public_path('storage/images/' . $image['filename']);
    }
}
