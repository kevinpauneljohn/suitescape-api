<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadImageRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Models\Image;
use App\Models\Listing;
use App\Models\Video;
use App\Services\ImageRetrievalService;
use App\Services\ImageUploadService;
use App\Services\ListingCreateService;
use App\Services\ListingLikeService;
use App\Services\ListingRetrievalService;
use App\Services\ListingSaveService;
use App\Services\ListingViewService;
use App\Services\VideoRetrievalService;
use App\Services\VideoUploadService;

class ListingController extends Controller
{
    private ListingRetrievalService $listingRetrievalService;
    private ImageRetrievalService $imageRetrievalService;
    private VideoRetrievalService $videoRetrievalService;
    private ImageUploadService $imageUploadService;
    private VideoUploadService $videoUploadService;

    public function __construct(ListingRetrievalService $listingRetrievalService, ImageRetrievalService $imageRetrievalService, VideoRetrievalService $videoRetrievalService, ImageUploadService $imageUploadService, VideoUploadService $videoUploadService)
    {
        $this->listingRetrievalService = $listingRetrievalService;
        $this->imageRetrievalService = $imageRetrievalService;
        $this->videoRetrievalService = $videoRetrievalService;
        $this->imageUploadService = $imageUploadService;
        $this->videoUploadService = $videoUploadService;

        $this->middleware('auth:sanctum')->only(['uploadListingImage', 'uploadListingVideo', 'likeListing', 'saveListing']);
    }

    public function getAllListings()
    {
        return $this->listingRetrievalService->getAllListings();
    }

    public function getListing(string $id)
    {
        return $this->listingRetrievalService->getListingComplete($id);
    }

    public function getListingHost(string $id)
    {
        return $this->listingRetrievalService->getListingHost($id);
    }

    public function getListingImages(string $id)
    {
        return $this->listingRetrievalService->getListingImages($id);
    }

    public function getListingVideos(string $id)
    {
        return $this->listingRetrievalService->getListingVideos($id);
    }

    public function getListingReviews(string $id)
    {
        return $this->listingRetrievalService->getListingReviews($id);
    }

    public function getListingImage(string $id, string $imageId)
    {
        $image = Image::findOrFail($imageId);

        $user = auth('sanctum')->user();
        if ($image->privacy === 'private' && (!$user || !$image->isOwnedBy($user))) {
            return response()->json([
                "message" => "You are not authorized to view this image."
            ], 403);
        }

        $imageUrl = $this->imageRetrievalService->getImageUrl($image);
        return response()->file($imageUrl);
    }

    public function getListingVideo(string $id, string $videoId)
    {
        $video = Video::findOrFail($videoId);
        $listing = $video->listing;

        $user = auth('sanctum')->user();
        if ($video->privacy === 'private' && (!$user || !$video->isOwnedBy($user))) {
            return response()->json([
                "message" => "You are not authorized to view this video."
            ], 403);
        }

        (new ListingViewService($listing, $user))->addView();

        return $this->videoRetrievalService->streamVideo($video);
    }

    public function uploadListingImage(UploadImageRequest $request, string $id)
    {
        $validated = $request->validated();

        $filename = $this->imageUploadService->upload($validated['image']);

        $image = (new ListingCreateService($id, $filename, $validated))->createListingImage();

        return response()->json([
            "message" => "Image uploaded successfully.",
            "image" => $image,
        ]);
    }

    public function uploadListingVideo(UploadVideoRequest $request, string $id)
    {
        $validated = $request->validated();

        $filename = $this->videoUploadService->upload($validated['video']);

        $video = (new ListingCreateService($id, $filename, $validated))->createListingVideo();

        return response()->json([
            "message" => "Video uploaded successfully.",
            "video" => $video,
        ]);
    }

    public function likeListing(string $id)
    {
        $listing = Listing::findOrFail($id);
        $listingLikeService = new ListingLikeService($listing);

        $user = auth()->user();

        if ($listing->isLikedBy($user)) {
            $listingLikeService->removeLike();
            return response()->json([
                "liked" => false,
                "message" => "Listing unliked."
            ]);
        }

        $listingLikeService->addLike();
        return response()->json([
            "liked" => true,
            "message" => "Listing liked."
        ]);
    }

    public function saveListing(string $id)
    {
        $listing = Listing::findOrFail($id);
        $listingSaveService = new ListingSaveService($listing);

        $user = auth()->user();

        if ($listing->isSavedBy($user)) {
            $listingSaveService->removeSave();
            return response()->json([
                "saved" => false,
                "message" => "Listing unsaved."
            ]);
        }

        $listingSaveService->addSave();
        return response()->json([
            "saved" => true,
            "message" => "Listing saved."
        ]);
    }
}
