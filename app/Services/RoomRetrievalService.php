<?php

namespace App\Services;

use App\Models\Room;

class RoomRetrievalService
{
    protected ?Room $currentRoom = null;

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
                'unavailableDates',
                'roomAmenities.amenity']);
    }

    public function getRoomListing(string $id)
    {
        return $this->getRoom($id)->listing;
    }
}
