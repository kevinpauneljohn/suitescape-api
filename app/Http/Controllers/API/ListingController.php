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
use App\Services\NotificationService;
use Exception;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    private ListingRetrievalService $listingRetrievalService;

    private ListingCreateService $listingCreateService;

    private ListingUpdateService $listingUpdateService;

    private ListingDeleteService $listingDeleteService;

    private NotificationService $notificationService;

    public function __construct(ListingRetrievalService $listingRetrievalService, ListingCreateService $listingCreateService, ListingUpdateService $listingUpdateService, ListingDeleteService $listingDeleteService, NotificationService $notificationService)
    {
        $this->middleware('auth:sanctum')->only(['createListing', 'updateListing', 'uploadListingImage', 'uploadListingVideo', 'likeListing', 'saveListing']);

        $this->listingRetrievalService = $listingRetrievalService;
        $this->listingCreateService = $listingCreateService;
        $this->listingUpdateService = $listingUpdateService;
        $this->listingDeleteService = $listingDeleteService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get All Listings
     *
     * Retrieves a collection of all listings available.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllListings()
    {
        return ListingResource::collection($this->listingRetrievalService->getAllListings());
    }

    /**
     * Get Listings By Host
     *
     * Retrieves listings for a specific host. This method requires the host's unique ID.
     * If no host ID is provided, it defaults to the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
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

    /**
     * Search Listings
     *
     * Performs a search on listings based on a query string and optional limit.
     * Returns a collection of listings that match the search criteria.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function searchListings(SearchRequest $request)
    {
        return ListingResource::collection($this->listingRetrievalService->searchListings(
            $request->validated('search_query'),
            $request->validated('limit')
        ));
    }

    /**
     * Get Listing
     *
     * Retrieves detailed information about a specific listing, including availability for a given date range.
     * The date range is used to calculate the current price of the entire place.
     *
     * @return ListingResource
     * @throws Exception
     */
    public function getListing(DateRangeRequest $request, string $id)
    {
        return new ListingResource($this->listingRetrievalService->getListingDetails($id, $request->validated()));
    }

    /**
     * Get Listing Rooms
     *
     * Retrieves a collection of rooms associated with a specific listing, considering availability for a given date range.
     * The date range is used for both calculating the price of each room and determining the rooms available during that period.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getListingRooms(DateRangeRequest $request, string $id)
    {
        return RoomResource::collection($this->listingRetrievalService->getListingRooms($id, $request->validated()));
    }

    //    public function getListingHost(string $id)
    //    {
    //        return new HostResource($this->listingRetrievalService->getListingHost($id));
    //    }

    /**
     * Get Listing Images
     *
     * Retrieves a collection of images associated with a specific listing.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getListingImages(string $id)
    {
        return ImageResource::collection($this->listingRetrievalService->getListingImages($id));
    }

    /**
     * Get Listing Videos
     *
     * Retrieves a collection of videos associated with a specific listing.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getListingVideos(string $id)
    {
        return VideoResource::collection($this->listingRetrievalService->getListingVideos($id));
    }

    /**
     * Get Listing Reviews
     *
     * Retrieves a collection of reviews written for a specific listing.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getListingReviews(string $id)
    {
        return ReviewResource::collection($this->listingRetrievalService->getListingReviews($id));
    }

    /**
     * Get Unavailable Dates
     *
     * Retrieves a collection of dates when a specific listing is unavailable, within a given date range.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getUnavailableDates(DateRangeRequest $request, string $id)
    {
        $unavailableDates = $this->listingRetrievalService->getUnavailableDatesFromRange($id, $request->validated('start_date'), $request->validated('end_date'));

        return UnavailableDateResource::collection($unavailableDates);
    }

    /**
     * Create Listing
     *
     * Creates a new listing based on the provided details.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function createListing(CreateListingRequest $request)
    {
        $listing = $this->listingCreateService->createListing($request->validated());

        $this->notificationService->createNotification([
            'user_id' => $listing->user_id,
            'title' => 'Listing Created',
            'message' => "Your listing \"$listing->name\" has been created successfully.",
            'type' => 'listing',
            'action_id' => $listing->id,
        ]);

        return response()->json([
            'message' => 'Listing created successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Update Listing
     *
     * Updates the details of an existing listing.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function updateListing(UpdateListingRequest $request, string $id)
    {
\Log::info('Raw POST request received', [
    'input' => $request->all(),
    'files' => $request->allFiles(),
    'headers' => $request->headers->all(),
]);
        $listing = $this->listingUpdateService->updateListing($id, $request->validated());

        $this->notificationService->createNotification([
            'user_id' => $listing->user_id,
            'title' => 'Listing Updated',
            'message' => "Your listing \"$listing->name\" has been updated successfully.",
            'type' => 'listing',
            'action_id' => $listing->id,
        ]);

        return response()->json([
            'message' => 'Listing updated successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Add Special Rate
     *
     * Adds a special rate to a listing for a specific date range.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addSpecialRate(CreateSpecialRateRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->addSpecialRate($id, $request->validated());

        return response()->json([
            'message' => 'Special rate added successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Update Special Rate
     *
     * Updates an existing special rate for a listing.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSpecialRate(CreateSpecialRateRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->updateSpecialRate($id, $request->validated());

        return response()->json([
            'message' => 'Special rate updated successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Remove Special Rate
     *
     * Removes a special rate from a listing.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeSpecialRate(Request $request, string $id)
    {
        $listing = $this->listingUpdateService->removeSpecialRate($id, $request->special_rate_id);

        return response()->json([
            'message' => 'Special rate removed successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Block Dates
     *
     * Blocks a range of dates for a listing, making it unavailable for booking.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockDates(DateRangeRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->blockDates($id, $request->validated());

        return response()->json([
            'message' => 'Listing dates blocked successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Unblock Dates
     *
     * Unblocks a previously blocked range of dates for a listing, making it available for booking again.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function unblockDates(DateRangeRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->unblockDates($id, $request->validated());

        return response()->json([
            'message' => 'Listing dates unblocked successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Update Prices
     *
     * Updates the pricing details of a listing.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePrices(UpdateListingPriceRequest $request, string $id)
    {
        $listing = $this->listingUpdateService->updatePrices($id, $request->validated());

        return response()->json([
            'message' => 'Listing prices updated successfully.',
            'listing' => new ListingResource($listing),
        ]);
    }

    /**
     * Delete Listing
     *
     * Deletes a listing.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function deleteListing(string $id)
    {
        $this->listingDeleteService->deleteListing($id);

        return response()->json([
            'message' => 'Listing deleted successfully.',
        ]);
    }

    /**
     * Upload Listing Image
     *
     * Uploads an image for a listing and associates it with the listing.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadListingImage(UploadImageRequest $request, string $id)
    {
        $image = $this->listingCreateService->createListingImage($id, $request->validated(), $request->file('image'));

        return response()->json([
            'message' => 'Listing image uploaded successfully.',
            'image' => new ImageResource($image),
        ]);
    }

    /**
     * Upload Listing Video
     *
     * Uploads a video for a listing and associates it with the listing.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadListingVideo(UploadVideoRequest $request, string $id)
    {
        $video = $this->listingCreateService->createListingVideo($id, $request->validated(), $request->file('video'));

        return response()->json([
            'message' => 'Listing video uploaded successfully.',
            'video' => new VideoResource($video),
        ]);
    }

    /**
     * Like Listing
     *
     * Allows a user to like a listing. If the listing is already liked by the user, it removes the like.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Save Listing
     *
     * Allows a user to save a listing for later viewing. If the listing is already saved by the user, it removes the save.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * View Listing
     *
     * Increments the view count of a listing. This is typically called when a listing is viewed by a user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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
