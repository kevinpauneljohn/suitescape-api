<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\HostResource;
use App\Http\Resources\ListingMetricResource;
use App\Http\Resources\ListingResource;
use App\Http\Resources\ReviewResource;
use App\Services\HostRetrievalService;

class HostController extends Controller
{
    private HostRetrievalService $hostRetrievalService;

    public function __construct(HostRetrievalService $hostRetrievalService)
    {
        $this->hostRetrievalService = $hostRetrievalService;
    }

    /**
     * Get All Hosts
     *
     * Retrieves a collection of all hosts. This includes detailed information for each host available in the system.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllHosts()
    {
        return HostResource::collection($this->hostRetrievalService->getAllHosts());
    }

    /**
     * Get Host
     *
     * Retrieves detailed information for a specific host identified by their unique ID. This includes personal details, contact information, and any other relevant data associated with the host.
     *
     * @param string $id
     * @return HostResource
     */
    public function getHost(string $id)
    {
        return new HostResource($this->hostRetrievalService->getHostDetails($id));
    }

    /**
     * Get Host Listings
     *
     * Retrieves a collection of listings owned by a specific host. This method requires the host's unique ID and returns all listings associated with them.
     *
     * @param string $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getHostListings(string $id)
    {
        return ListingResource::collection($this->hostRetrievalService->getHostListings($id));
    }

    /**
     * Get Host Reviews
     *
     * Retrieves all reviews written for a specific host. This method requires the host's unique ID and returns a collection of reviews from guests who have stayed with them.
     *
     * @param string $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getHostReviews(string $id)
    {
        return ReviewResource::collection($this->hostRetrievalService->getHostReviews($id));
    }

    /**
     * Get Host Likes
     *
     * Retrieves a collection of `ListingLike` models associated with the listings of a specific host.
     * This method requires the host's unique ID.
     *
     * @param string $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getHostLikes(string $id)
    {
        return ListingMetricResource::collection($this->hostRetrievalService->getHostLikes($id));
    }

    /**
     * Get Host Saves
     *
     * Retrieves a collection of `ListingSave` models associated with the listings of a specific host.
     * This method requires the host's unique ID.
     *
     * @param string $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getHostSaves(string $id)
    {
        return ListingMetricResource::collection($this->hostRetrievalService->getHostSaves($id));
    }

    /**
     * Get Host Views
     *
     * Retrieves a collection of `ListingView` models written for the listings of a specific host.
     * This method requires the host's unique ID.
     *
     * @param string $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getHostViews(string $id)
    {
        return ListingMetricResource::collection($this->hostRetrievalService->getHostViews($id));
    }
}
