<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\Room;
use App\Models\SpecialRate;
use InvalidArgumentException;

class SpecialRateService
{
    public function addSpecialRate(string $type, string $id, array $specialRate): void
    {
        $entity = $this->getEntity($type, $id);
        $basePrice = $entity->getCurrentBasePrice($specialRate['start_date']);

        if ($specialRate['price'] == $basePrice) {
            throw new InvalidArgumentException('Special rate price must be different from the base price.');
        }

        // If the special rate already exists exactly within the range, update it
        if ($this->updateSpecialRate($type, $id, $specialRate)) {
            return;
        }

        // Check if a special rate exists anywhere in the given date range
        if ($this->specialRateExists($type, $id, $specialRate['start_date'], $specialRate['end_date'])) {
            throw new InvalidArgumentException('A special rate already exists somewhere in the given date range.');
        }

        // Create the special rate for the entity
        $entity->specialRates()->create($specialRate);
    }

    public function updateSpecialRate(string $type, string $id, array $specialRate)
    {
        $entity = $this->getEntity($type, $id);

        // Check if the special rate exists
        $currentSpecialRate = $entity->getCurrentSpecialRate($specialRate['start_date'], $specialRate['end_date']);

        // If the special rate doesn't exist, return null
        if (! $currentSpecialRate) {
            return null;
        }

        // Maintain the same start and end dates
        $currentSpecialRate->update(array_merge($specialRate, [
            'start_date' => $currentSpecialRate->start_date,
            'end_date' => $currentSpecialRate->end_date,
        ]));

        return $currentSpecialRate;
    }

    public function removeSpecialRate(string $type, string $id, string $specialRateId): void
    {
        $this->validateType($type);

        SpecialRate::where($type.'_id', $id)
            ->where('id', $specialRateId)
            ->delete();
    }

    private function getEntity(string $type, string $id)
    {
        if ($type === 'listing') {
            return Listing::findOrFail($id);
        }

        if ($type === 'room') {
            return Room::findOrFail($id);
        }

        throw new InvalidArgumentException('Invalid type specified.');
    }

    private function validateType(string $type): void
    {
        $validTypes = ['listing', 'room'];
        if (! in_array($type, $validTypes)) {
            throw new InvalidArgumentException('Invalid type specified.');
        }
    }

    private function specialRateExists(string $type, string $id, string $startDate, string $endDate): bool
    {
        return SpecialRate::where($type.'_id', $id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $endDate)
                            ->where('end_date', '>=', $startDate);
                    });
            })
            ->exists();
    }
}
