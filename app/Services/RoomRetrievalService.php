<?php

namespace App\Services;

use App\Models\Room;

class RoomRetrievalService
{
    protected PriceCalculatorService $priceCalculatorService;

    protected UnavailableDateService $unavailableDateService;

    protected ?Room $currentRoom = null;

    public function __construct(PriceCalculatorService $priceCalculatorService, UnavailableDateService $unavailableDateService)
    {
        $this->priceCalculatorService = $priceCalculatorService;
        $this->unavailableDateService = $unavailableDateService;
    }

    public function getAllRooms()
    {
        return Room::all();
    }

    public function getRoom(string $id)
    {
        if (! $this->currentRoom || $this->currentRoom->id !== $id) {
            $this->currentRoom = Room::findOrFail($id);
        }

        return $this->currentRoom
            ->load([
                'roomRule',
                'roomCategory',
                'specialRates',
                'unavailableDates',
                'roomAmenities.amenity']);
    }

    public function getRoomDetails(string $id, array $filters = [])
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $room = $this->getRoom($id);

        // Load room category with price
        $room->load([
            'roomRule',
            'roomCategory' => function ($query) use ($startDate, $endDate) {
                return $this->priceCalculatorService->getPriceForRoomCategoriesToQuery($query, $startDate, $endDate);
            },
            'specialRates',
            'unavailableDates',
            'roomAmenities.amenity']);

        return $room;
    }

    public function getRoomListing(string $id)
    {
        return $this->getRoom($id)->listing;
    }

    public function getUnavailableDatesFromRange(string $id, string $startDate, string $endDate)
    {
        return $this->unavailableDateService->getUnavailableDatesFromRange('room', $id, $startDate, $endDate);
    }
}
