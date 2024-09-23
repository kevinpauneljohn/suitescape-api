<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Services\ImageRetrievalService;
use App\Services\MediaUploadService;
use Spatie\Permission\Exceptions\UnauthorizedException;

class ImageController extends Controller
{
    private ImageRetrievalService $imageRetrievalService;

    private MediaUploadService $mediaUploadService;

    public function __construct(ImageRetrievalService $imageRetrievalService, MediaUploadService $mediaUploadService)
    {
        $this->middleware('auth:sanctum')->only(['uploadImage']);

        $this->imageRetrievalService = $imageRetrievalService;
        $this->mediaUploadService = $mediaUploadService;
    }

    /**
     * Get All Images
     *
     * Retrieves a collection of all images. This method does not require authentication and returns all images regardless of their privacy settings.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllImages()
    {
        return ImageResource::collection($this->imageRetrievalService->getAllImages());
    }

    /**
     * Get Image
     *
     * Retrieves a specific image by its unique ID. This method checks the image's privacy settings and the user's permission to view the image. If the image is private and the user does not have permission, it throws an UnauthorizedException.
     *
     * @param string $id
     * @return ImageResource
     */
    public function getImage(string $id)
    {
        $image = Image::findOrFail($id);

        $user = auth('sanctum')->user();
        if ($image->privacy === 'private' && (! $user || ! $image->isOwnedBy($user))) {
            throw new UnauthorizedException(403, 'You do not have permission to view this image.');
        }
        //        $imagePath = $this->imageRetrievalService->getImagePath($image->filename);

        //        return response()->file($imagePath);
        return new ImageResource($image);
    }

    /**
     * Upload Image
     *
     * Allows authenticated users to upload a new image. The method expects an image file in the request and returns the filename of the uploaded image upon success.
     *
     * @param UploadImageRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(UploadImageRequest $request)
    {
        $filename = $this->mediaUploadService->upload($request->file('image'), 'images');

        return response()->json([
            'message' => 'Image uploaded successfully',
            'filename' => $filename,
        ]);
    }
}
