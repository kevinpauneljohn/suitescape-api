<?php

namespace App\Services;

use App\Models\UnavailableDate;
use Carbon\CarbonPeriod;
use Exception;
use InvalidArgumentException;

class UnavailableDateService
{
    protected function validateType(string $type): void
    {
        $validTypes = ['listing', 'room', 'booking'];
        if (! in_array($type, $validTypes)) {
            throw new InvalidArgumentException('Invalid type specified.');
        }
    }

    /**
     * @throws Exception
     */
    protected function createUnavailableDatesFromRange($booking, string $type, string $id, string $startDate, string $endDate, string $dateType): void
    {
        $this->validateType($type);

        $period = CarbonPeriod::create($startDate, $endDate);
        $createdCount = 0;

        foreach ($period as $date) {
            $query = UnavailableDate::query();

            if ($booking) {
                $query->where('booking_id', $booking->id);
            }

            $existingUnavailableDate = $query->where($type.'_id', $id)
                ->where('date', $date->format('Y-m-d'))
                ->first();

            if (! $existingUnavailableDate) {
                UnavailableDate::create([
                    $type.'_id' => $id,
                    'type' => $dateType,
                    'date' => $date->format('Y-m-d'),
                ]);
                $createdCount++; // Increment the counter
            }
        }

        // After the loop, check if any unavailable dates were created
        if ($createdCount === 0) {
            throw new Exception('Dates already blocked.');
        }
    }

    public function getUnavailableDatesFromRange(string $type, string $id, string $startDate, string $endDate)
    {
        $this->validateType($type);

        return UnavailableDate::where($type.'_id', $id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
    }

    public function addUnavailableDates(string $type, string $id, string $startDate, string $endDate): void
    {
        $this->createUnavailableDatesFromRange(null, $type, $id, $startDate, $endDate, 'blocked');
    }

    public function addUnavailableDatesForBooking($booking, string $type, string $id, string $startDate, string $endDate): void
    {
        $this->createUnavailableDatesFromRange($booking, $type, $id, $startDate, $endDate, 'booked');
    }

    /**
     * @throws Exception
     */
    public function removeUnavailableDates(string $type, string $id, string $startDate, string $endDate): void
    {
        $this->validateType($type);

        $unavailableDates = UnavailableDate::where($type.'_id', $id)
            ->where('type', 'blocked')
            ->whereBetween('date', [$startDate, $endDate]);

        if (! $unavailableDates->exists()) {
            throw new Exception('No dates were found to unblock.');
        }

        $unavailableDates->delete();
    }

    public function removeUnavailableDatesForBooking($booking): void
    {
        $booking->unavailableDates()->delete();
    }
}
