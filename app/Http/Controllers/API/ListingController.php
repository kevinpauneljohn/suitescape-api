<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\UploadImageRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\ImageResource;
use App\Http\Resources\ListingResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\VideoResource;
use App\Models\Listing;
use App\Services\ImageUploadService;
use App\Services\ListingCreateService;
use App\Services\ListingLikeService;
use App\Services\ListingRetrievalService;
use App\Services\ListingSaveService;
use App\Services\ListingViewService;
use App\Services\SettingsService;
use App\Services\VideoUploadService;

class ListingController extends Controller
{
    private ListingRetrievalService $listingRetrievalService;

    private ImageUploadService $imageUploadService;

    private VideoUploadService $videoUploadService;

    private SettingsService $settingsService;

    public function __construct(ListingRetrievalService $listingRetrievalService, ImageUploadService $imageUploadService, VideoUploadService $videoUploadService, SettingsService $settingsService)
    {
        $this->middleware('auth:sanctum')->only(['uploadListingImage', 'uploadListingVideo', 'likeListing', 'saveListing']);

        $this->listingRetrievalService = $listingRetrievalService;
        $this->imageUploadService = $imageUploadService;
        $this->videoUploadService = $videoUploadService;
        $this->settingsService = $settingsService;
    }

    public function getAllListings()
    {
        return ListingResource::collection($this->listingRetrievalService->getAllListings());
    }

    public function searchListings(SearchRequest $request)
    {
        return ListingResource::collection($this->listingRetrievalService->searchListings($request->search_query, $request->limit));
    }

    public function getListing(string $id)
    {
        $cancellationPolicy = $this->settingsService->getSetting('cancellation_policy')->value;

        return new ListingResource($this->listingRetrievalService->getListingDetails($id), $cancellationPolicy);
    }

    public function getListingRooms(string $id)
    {
        return RoomResource::collection($this->listingRetrievalService->getListingRooms($id));
    }

    //    public function getListingHost(string $id)
    //    {
    //        return new HostResource($this->listingRetrievalService->getListingHost($id));
    //    }

    public function getListingImages(string $id)
    {
        return ImageResource::collection($this->listingRetrievalService->getListingImages($id));
    }

    public function getListingVideos(string $id)
    {
        return VideoResource::collection($this->listingRetrievalService->getListingVideos($id));
    }

    public function getListingReviews(string $id)
    {
        return ReviewResource::collection($this->listingRetrievalService->getListingReviews($id));
    }

    public function uploadListingImage(UploadImageRequest $request, string $id)
    {
        $validated = $request->validated();

        $filename = $this->imageUploadService->upload($request->file('image'));
        $image = (new ListingCreateService($id, $filename, $validated))->createListingImage();

        return response()->json([
            'message' => 'Listing image uploaded successfully.',
            'image' => $image,
        ]);
    }

    public function uploadListingVideo(UploadVideoRequest $request, string $id)
    {
        $validated = $request->validated();

        $filename = $this->videoUploadService->upload($request->file('video'));
        $video = (new ListingCreateService($id, $filename, $validated))->createListingVideo();

        return response()->json([
            'message' => 'Listing video uploaded successfully.',
            'video' => $video,
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
                'liked' => false,
                'message' => 'Listing unliked.',
            ]);
        }

        $listingLikeService->addLike();

        return response()->json([
            'liked' => true,
            'message' => 'Listing liked.',
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
                'saved' => false,
                'message' => 'Listing unsaved.',
            ]);
        }

        $listingSaveService->addSave();

        return response()->json([
            'saved' => true,
            'message' => 'Listing saved.',
        ]);
    }

    public function viewListing(string $id)
    {
        $listing = Listing::findOrFail($id);
        $listingViewService = new ListingViewService($listing);

        if (! $listingViewService->addView()) {
            return response()->json([
                'viewed' => false,
                'message' => 'Error viewing listing.',
            ]);
        }

        return response()->json([
            'viewed' => true,
            'message' => 'Listing viewed.',
        ]);
    }
}
