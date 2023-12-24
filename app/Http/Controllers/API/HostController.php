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

    public function getAllHosts()
    {
        return HostResource::collection($this->hostRetrievalService->getAllHosts());
    }

    public function getHost(string $id)
    {
        return new HostResource($this->hostRetrievalService->getHostDetails($id));
    }

    public function getHostListings(string $id)
    {
        return ListingResource::collection($this->hostRetrievalService->getHostListings($id));
    }

    public function getHostReviews(string $id)
    {
        return ReviewResource::collection($this->hostRetrievalService->getHostReviews($id));
    }

    public function getHostLikes(string $id)
    {
        return ListingMetricResource::collection($this->hostRetrievalService->getHostLikes($id));
    }

    public function getHostSaves(string $id)
    {
        return ListingMetricResource::collection($this->hostRetrievalService->getHostSaves($id));
    }

    public function getHostViews(string $id)
    {
        return ListingMetricResource::collection($this->hostRetrievalService->getHostViews($id));
    }
}
