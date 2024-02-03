<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\RoomResource;
use App\Services\RoomRetrievalService;

class RoomController extends Controller
{
    private RoomRetrievalService $roomRetrievalService;

    public function __construct(RoomRetrievalService $roomRetrievalService)
    {
        $this->roomRetrievalService = $roomRetrievalService;
    }

    public function getAllRooms()
    {
        return RoomResource::collection($this->roomRetrievalService->getAllRooms());
    }

    public function getRoom(string $id)
    {
        return new RoomResource($this->roomRetrievalService->getRoom($id));
    }

    public function getRoomListing(string $id)
    {
        return new ListingResource($this->roomRetrievalService->getRoomListing($id));
    }

    public function getRoomReviews(string $id)
    {
        return ReviewResource::collection($this->roomRetrievalService->getRoomReviews($id));
    }
}
