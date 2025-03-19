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
                    'booking_id' => $booking ? $booking->id : null,
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

    public function getUnavailableDatesFromRange(string $type, string $id, string $startDate, string $endDate, bool $excludeUser = false)
    {
        $this->validateType($type);

        return UnavailableDate::where($type.'_id', $id)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($excludeUser, function ($query) {
                return $query->whereHas('booking', function ($query) {
                    return $query->where('user_id', '!=', auth('sanctum')->id());
                });
            })
            ->get();
    }

    /**
     * @throws Exception
     */
    public function addUnavailableDates(string $type, string $id, string $startDate, string $endDate): void
    {
        $this->createUnavailableDatesFromRange(null, $type, $id, $startDate, $endDate, 'blocked');
    }

    public function removeUnavailableDates(string $type, string $id, string $startDate, string $endDate): void
    {
        $this->validateType($type);

        $unavailableDates = UnavailableDate::where($type.'_id', $id)
            ->where('type', 'blocked')
            ->whereBetween('date', [$startDate, $endDate]);

        $unavailableDates->delete();
    }

    public function getUnavailableDatesForBooking($booking, bool $excludeUser = false)
    {
        // Get unavailable dates for entire place
        if ($booking->listing->is_entire_place) {
            return $this->getUnavailableDatesFromRange(
                'listing',
                $booking->listing->id,
                $booking->date_start,
                $booking->date_end,
                $excludeUser
            );
        }

        // Get unavailable dates for each room
        $unavailableDates = collect();
        foreach ($booking->rooms as $room) {
            $unavailableDates = $unavailableDates->merge(
                $this->getUnavailableDatesFromRange(
                    'room',
                    $room->id,
                    $booking->date_start,
                    $booking->date_end,
                    $excludeUser
                )
            );
        }

        return $unavailableDates;
    }

    /**
     * @throws Exception
     */
    public function addUnavailableDatesForBooking($booking, string $type, string $id, string $startDate, string $endDate): void
    {
        $this->createUnavailableDatesFromRange($booking, $type, $id, $startDate, $endDate, 'booked');
    }

    public function removeUnavailableDatesForBooking($booking): void
    {
        $booking->unavailableDates()->delete();
    }
}
