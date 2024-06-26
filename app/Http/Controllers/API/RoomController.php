<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSpecialRateRequest;
use App\Http\Requests\DateRangeRequest;
use App\Http\Requests\UpdateRoomPriceRequest;
use App\Http\Resources\ListingResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\UnavailableDateResource;
use App\Services\RoomRetrievalService;
use App\Services\RoomUpdateService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    private RoomRetrievalService $roomRetrievalService;

    private RoomUpdateService $roomUpdateService;

    public function __construct(RoomRetrievalService $roomRetrievalService, RoomUpdateService $roomUpdateService)
    {
        $this->roomRetrievalService = $roomRetrievalService;
        $this->roomUpdateService = $roomUpdateService;
    }

    public function getAllRooms()
    {
        return RoomResource::collection($this->roomRetrievalService->getAllRooms());
    }

    public function getRoom(DateRangeRequest $request, string $id)
    {
        return new RoomResource($this->roomRetrievalService->getRoomDetails($id, $request->validated()));
    }

    public function getRoomListing(string $id)
    {
        return new ListingResource($this->roomRetrievalService->getRoomListing($id));
    }

    public function getUnavailableDates(DateRangeRequest $request, string $id)
    {
        $unavailableDates = $this->roomRetrievalService->getUnavailableDatesFromRange($id, $request->validated()['start_date'], $request->validated()['end_date']);

        return UnavailableDateResource::collection($unavailableDates);
    }

    public function addSpecialRate(CreateSpecialRateRequest $request, string $id)
    {
        $room = $this->roomUpdateService->addSpecialRate($id, $request->validated());

        return response()->json([
            'message' => 'Special rate added successfully.',
            'room' => new RoomResource($room),
        ]);
    }

    public function updateSpecialRate(CreateSpecialRateRequest $request, string $id)
    {
        $room = $this->roomUpdateService->updateSpecialRate($id, $request->validated());

        return response()->json([
            'message' => 'Special rate updated successfully.',
            'room' => new RoomResource($room),
        ]);
    }

    public function removeSpecialRate(Request $request, string $id)
    {
        $room = $this->roomUpdateService->removeSpecialRate($id, $request->special_rate_id);

        return response()->json([
            'message' => 'Special rate removed successfully.',
            'room' => new RoomResource($room),
        ]);
    }

    public function blockDates(DateRangeRequest $request, string $id)
    {
        $room = $this->roomUpdateService->blockDates($id, $request->validated());

        return response()->json([
            'message' => 'Room dates blocked successfully.',
            'room' => new RoomResource($room),
        ]);
    }

    public function unblockDates(DateRangeRequest $request, string $id)
    {
        $room = $this->roomUpdateService->unblockDates($id, $request->validated());

        return response()->json([
            'message' => 'Room dates unblocked successfully.',
            'room' => new RoomResource($room),
        ]);
    }

    public function updatePrices(UpdateRoomPriceRequest $request, string $id)
    {
        $room = $this->roomUpdateService->updatePrices($id, $request->validated());

        return response()->json([
            'message' => 'Room prices updated successfully.',
            'room' => new RoomResource($room),
        ]);
    }
}
