<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Services\ImageRetrievalService;
use App\Services\ImageUploadService;
use Spatie\Permission\Exceptions\UnauthorizedException;

class ImageController extends Controller
{
    private ImageRetrievalService $imageRetrievalService;

    private ImageUploadService $imageUploadService;

    public function __construct(ImageRetrievalService $imageRetrievalService, ImageUploadService $imageUploadService)
    {
        $this->middleware('auth:sanctum')->only(['uploadImage']);

        $this->imageRetrievalService = $imageRetrievalService;
        $this->imageUploadService = $imageUploadService;
    }

    public function getAllImages()
    {
        return ImageResource::collection($this->imageRetrievalService->getAllImages());
    }

    public function getImage(string $id)
    {
        $image = Image::findOrFail($id);

        $user = auth('sanctum')->user();
        if ($image->privacy === 'private' && (! $user || ! $image->isOwnedBy($user))) {
            throw new UnauthorizedException(403, 'You do not have permission to view this image.');
        }

        $imageUrl = $this->imageRetrievalService->getImageUrl($image);

        return response()->file($imageUrl);
    }

    public function uploadImage(UploadImageRequest $request)
    {
        return $this->imageUploadService->upload($request->file('image'));
    }
}
