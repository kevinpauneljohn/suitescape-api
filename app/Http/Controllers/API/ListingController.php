<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateListingRequest;
use App\Http\Requests\CreateSpecialRateRequest;
use App\Http\Requests\DateRangeRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\UpdateListingPriceRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Requests\UploadImageRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\ImageResource;
use App\Http\Resources\ListingResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\UnavailableDateResource;
use App\Http\Resources\VideoResource;
use App\Models\Listing;
use App\Services\ListingCreateService;
use App\Services\ListingDeleteService;
use App\Services\ListingLikeService;
use App\Services\ListingRetrievalService;
use App\Services\ListingSaveService;
use App\Services\ListingUpdateService;
use App\Services\ListingViewService;
use Exception;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    private ListingRetrievalService $listingRetrievalService;

    private ListingCreateService $listingCreateService;

    private ListingUpdateService $listingUpdateService;

    private ListingDeleteService $listingDeleteService;

    public function __construct(ListingRetrievalService $listingRetrievalService, ListingCreateService $listingCreateService, ListingUpdateService $listingUpdateService, ListingDeleteService $listingDeleteService)
    {
        $this->middleware('auth:sanctum')->only(['createListing', 'uploadListingImage', 'uploadListingVideo', 'likeListing', 'saveListing']);

        $this->listingRetrievalService = $listingRetrievalService;
        $this->listingCreateService = $listingCreateService;
        $this->listingUpdateService = $listingUpdateService;
        $this->listingDeleteService = $listingDeleteService;
    }

    public function getAllListings()
    {
        return ListingResource::collection($this->listingRetrievalService->getAllListings());
    }

    public function getListingsByHost(Request $request)
    {
        // If no host id is provided, default to the authenticated user
        $hostId = $request->id ?? auth('sanctum')->id();

        if (! $hostId) {
            return response()->json([
                'message' => 'No host id provided.',
            ], 400);
        }

        return ListingResource::collection($this->listingRetrievalService->getListingsByHost($hostId));
    }

    public function searchListings(SearchRequest $request)
    {
        return ListingResource::collection($this->listingRetrievalService->searchListings($request->search_query, $request->limit));
    }

    public function getListing(DateRangeRequest $request, string $id)
    {
        return new ListingResource($this->listingRetrievalService->getListingDetails($id, $request->validated()));
    }

    public function getListingRooms(DateRangeRequest $request, string $id)
    {
        return RoomResource::collection($this->listingRetrievalService->getListingRooms($id, $request->validated()));
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

    public function getUnavailableDates(DateRangeRequest $request, string $id)
    {
        $unavailableDates = $this->listingRetrievalService->getUnavailableDatesFromRange($id, $request->validated()['start_date'], $request->validated()['end_date']);

        return UnavailableDateResource::collection($unavailableDates);
    }

    /**
     * @throws Exception
     */
    public function createListing(CreateListingRequest $request)
    {
        $listing = $this->listingCreateService->createListing($request->validated());

        return response()->json([
            'message' => 'Listing created successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * @throws Exception
     */
    public function updateListing(UpdateListingRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->updateListing($id, $request->validated());

        return response()->json([
            'message' => 'Listing updated successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    public function addSpecialRate(CreateSpecialRateRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->addSpecialRate($id, $request->validated());

        return response()->json([
            'message' => 'Special rate added successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    public function updateSpecialRate(CreateSpecialRateRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->updateSpecialRate($id, $request->validated());

        return response()->json([
            'message' => 'Special rate updated successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    public function removeSpecialRate(Request $request, string $id)
    {
        $listing = $this->listingUpdateService->removeSpecialRate($id, $request->special_rate_id);

        return response()->json([
            'message' => 'Special rate removed successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    public function blockDates(DateRangeRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->blockDates($id, $request->validated());

        return response()->json([
            'message' => 'Listing dates blocked successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    public function unblockDates(DateRangeRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->unblockDates($id, $request->validated());

        return response()->json([
            'message' => 'Listing dates unblocked successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    public function updatePrices(UpdateListingPriceRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->updatePrices($id, $request->validated());

        return response()->json([
            'message' => 'Listing prices updated successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * @throws Exception
     */
    public function deleteListing(string $id)
    {
        $this->listingDeleteService->deleteListing($id);

        return response()->json([
            'message' => 'Listing deleted successfully.',
        ]);
    }

    public function uploadListingImage(UploadImageRequest $request, string $id)
    {
        $image = $this->listingCreateService->createListingImage($id, $request->validated(), $request->file('image'));

        return response()->json([
            'message' => 'Listing image uploaded successfully.',
            'image' => new ImageResource($image),
        ]);
    }

    public function uploadListingVideo(UploadVideoRequest $request, string $id)
    {
        $video = $this->listingCreateService->createListingVideo($id, $request->validated(), $request->file('video'));

        return response()->json([
            'message' => 'Listing video uploaded successfully.',
            'video' => new VideoResource($video),
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
