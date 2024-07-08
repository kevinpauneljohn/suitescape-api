<?php

namespace App\Services;

use App\Models\Room;
use Exception;

class RoomUpdateService
{
    protected SpecialRateService $specialRateService;

    protected UnavailableDateService $unavailableDateService;

    public function __construct(SpecialRateService $specialRateService, UnavailableDateService $unavailableDateService)
    {
        $this->specialRateService = $specialRateService;
        $this->unavailableDateService = $unavailableDateService;
    }

    public function addSpecialRate(string $roomId, array $specialRate)
    {
        $room = Room::findOrFail($roomId);

        $this->specialRateService->addSpecialRate('room', $roomId, $specialRate);

        return $room;
    }

    public function updateSpecialRate(string $roomId, array $specialRate)
    {
        $room = Room::findOrFail($roomId);

        $this->specialRateService->updateSpecialRate('room', $roomId, $specialRate);

        return $room;
    }

    public function removeSpecialRate(string $roomId, string $specialRateId)
    {
        $room = Room::findOrFail($roomId);

        $this->specialRateService->removeSpecialRate('room', $roomId, $specialRateId);

        return $room;
    }

    /**
     * @throws Exception
     */
    public function blockDates(string $roomId, array $dates)
    {
        $room = Room::findOrFail($roomId);

        $this->unavailableDateService->addUnavailableDates('room', $roomId, $dates['start_date'], $dates['end_date']);

        return $room;
    }

    /**
     * @throws Exception
     */
    public function unblockDates(string $roomId, array $dates)
    {
        $room = Room::findOrFail($roomId);

        $this->unavailableDateService->removeUnavailableDates('room', $roomId, $dates['start_date'], $dates['end_date']);

        return $room;
    }

    public function updatePrices(string $roomId, array $prices)
    {
        $room = Room::findOrFail($roomId);

        $room->roomCategory()->update($prices);

        return $room;
    }
}
