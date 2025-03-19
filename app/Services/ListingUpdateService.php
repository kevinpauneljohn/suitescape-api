<?php

namespace App\Services;

use App\Models\Listing;
use Exception;
use Log;

class ListingUpdateService
{
    protected FileNameService $fileNameService;

    protected ListingCreateService $listingCreateService;

    protected SpecialRateService $specialRateService;

    protected UnavailableDateService $unavailableDateService;

    public function __construct(FileNameService $fileNameService, ListingCreateService $listingCreateService, SpecialRateService $specialRateService, UnavailableDateService $unavailableDateService)
    {
        $this->fileNameService = $fileNameService;
        $this->listingCreateService = $listingCreateService;
        $this->specialRateService = $specialRateService;
        $this->unavailableDateService = $unavailableDateService;
    }

    /**
     * @throws Exception
     */
    public function updateListing(string $listingId, array $listingData): Listing
    {
        // Add the authenticated user's ID to the listing data
        $listingData['user_id'] = auth()->user()->id;

        // Find the existing listing and update it
        $listing = Listing::updateOrCreate(['id' => $listingId], $listingData);

        if (isset($listingData['rooms'])) {
            $this->updateListingRooms($listing, $listingData['rooms']);
        }

        if (isset($listingData['images'])) {
            $this->updateListingImages($listing, $listingData['images']);
        }

        if (isset($listingData['videos'])) {
            $this->updateListingVideos($listing, $listingData['videos']);
        }

        if (isset($listingData['addons'])) {
            $this->updateListingAddons($listing, $listingData['addons']);
        }

        if (isset($listingData['nearby_places'])) {
            $this->updateListingNearbyPlaces($listing, $listingData['nearby_places']);
        }

        return $listing;
    }

    public function addSpecialRate(string $listingId, array $specialRate)
    {
        $listing = Listing::findOrFail($listingId);

        $this->specialRateService->addSpecialRate('listing', $listingId, $specialRate);

        return $listing;
    }

    public function updateSpecialRate(string $listingId, array $specialRate)
    {
        $listing = Listing::findOrFail($listingId);

        $this->specialRateService->updateSpecialRate('listing', $listingId, $specialRate);

        return $listing;
    }

    public function removeSpecialRate(string $listingId, string $specialRateId)
    {
        $listing = Listing::findOrFail($listingId);

        $this->specialRateService->removeSpecialRate('listing', $listingId, $specialRateId);

        return $listing;
    }

    /**
     * @throws Exception
     */
    public function blockDates(string $listingId, array $dates)
    {
        $listing = Listing::findOrFail($listingId);

        $this->unavailableDateService->addUnavailableDates('listing', $listingId, $dates['start_date'], $dates['end_date']);

        return $listing;
    }

    public function unblockDates(string $listingId, array $dates)
    {
        $listing = Listing::findOrFail($listingId);

        $this->unavailableDateService->removeUnavailableDates('listing', $listingId, $dates['start_date'], $dates['end_date']);

        return $listing;
    }

    public function updatePrices(string $listingId, array $prices)
    {
        $listing = Listing::findOrFail($listingId);

        $listing->update($prices);

        return $listing;
    }

    public function updateListingImages($listing, $listingImages): void
    {
        // Create a map with id as keys for quick lookup
        $newImagesMap = collect($listingImages)
            ->filter(fn ($image) => ! isset($image['file']))
            ->keyBy('id')
            ->toArray();

        // Loop through the images of the listing
        foreach ($listing->images as $image) {
            // If the image does not exist in the new data, delete it
            if (! isset($newImagesMap[$image->id])) {
                $image->delete();

                continue;
            }

            // If the image exists in the new data, update it and remove it from the map
            $image->update($newImagesMap[$image->id]);
            unset($newImagesMap[$image->id]);
        }

        // Any remaining items in the map are new images that need to be added
        $newImages = array_filter($listingImages, fn ($image) => isset($image['file']));
        foreach ($newImages as $newImage) {
            $this->listingCreateService->createListingImage($listing->id, $newImage, $newImage['file']);
        }
    }

    public function updateListingVideos($listing, $listingVideos): void
    {
        // Create a map with id as keys for quick lookup
        $newVideosMap = collect($listingVideos)
            ->filter(fn ($video) => ! isset($video['file']))
            ->keyBy('id')
            ->toArray();

        // Loop through the videos of the listing
        foreach ($listing->videos as $video) {
            // If the video does not exist in the new data, delete it
            if (! isset($newVideosMap[$video->id])) {
                $video->delete();

                continue;
            }

            // If the video exists in the new data, update it and remove it from the map
            $video->update($newVideosMap[$video->id]);
            unset($newVideosMap[$video->id]);
        }

        // Any remaining items in the map are new videos that need to be added
        $newVideos = array_filter($listingVideos, fn ($video) => isset($video['file']));
        foreach ($newVideos as $newVideo) {
            $this->listingCreateService->createListingVideo($listing->id, $newVideo, $newVideo['file']);
        }
    }

    /**
     * @throws Exception
     */
    public function updateListingRooms($listing, $listingRooms): void
    {
        foreach ($listingRooms as $room) {
            if (isset($room['category'])) {
                $this->updateRoomCategory($listing, $room['category']);
            }

            if (! isset($room['id'])) {
                Log::error('Room ID not provided.');

                continue;
            }

            $listingRoom = $listing->rooms()->find($room['id']);

            if (isset($room['rule'])) {
                $this->updateRoomRule($listingRoom, $room['rule']);
            }

            if (isset($room['amenities'])) {
                $this->updateRoomAmenities($listingRoom, $room['amenities']);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function updateRoomCategory($listing, $roomCategory): void
    {
        $roomCategory['type_of_beds'] = $this->filterBeds($roomCategory);

        $listing->roomCategories()->updateOrCreate([
            'id' => $roomCategory['id'],
        ], $roomCategory);
    }

    public function updateRoomRule($room, $roomRule): void
    {
        $room->roomRule()->update($roomRule);
    }

    /**
     * @throws Exception
     */
    public function updateRoomAmenities($room, $amenities): void
    {
        $newAmenities = array_filter($amenities);

        // Loop through the amenities of the room
        foreach ($room->roomAmenities as $roomAmenity) {
            $amenityName = $roomAmenity->amenity->name;

            // If the amenity does not exist in the new data, delete it
            if (! isset($newAmenities[$amenityName])) {
                $roomAmenity->delete();
            } else {
                // If the amenity exists in the new data, remove it from the new amenities
                unset($newAmenities[$amenityName]);
            }
        }

        // Any remaining items in the new amenities are new amenities that need to be added
        $this->listingCreateService->createRoomAmenities($room, $newAmenities);
    }

    public function updateListingNearbyPlaces($listing, $nearbyPlaces): void
    {
        $newNearbyPlaces = array_filter($nearbyPlaces);

        // Loop through the nearby places of the listing
        foreach ($listing->listingNearbyPlaces as $listingNearbyPlace) {
            $nearbyPlaceName = $listingNearbyPlace->nearbyPlace->name;

            // If the nearby place does not exist in the new data, delete it
            if (! isset($newNearbyPlaces[$nearbyPlaceName])) {
                $listingNearbyPlace->delete();
            } else {
                // If the nearby place exists in the new data, remove it from the new nearby places
                unset($newNearbyPlaces[$nearbyPlaceName]);
            }
        }

        // Any remaining items in the new nearby places are new nearby places that need to be added
        $this->listingCreateService->createListingNearbyPlaces($listing, $newNearbyPlaces);
    }

    public function updateListingAddons($listing, $addons): void
    {
        $newAddons = collect($addons)->keyBy('id')->toArray();

        // Loop through the addons of the listing
        foreach ($listing->addons as $listingAddon) {
            // If the addon does not exist in the new data, delete it
            if (! isset($newAddons[$listingAddon->id])) {
                $listingAddon->delete();

                continue;
            }

            // If the addon exists in the new data, update it and remove it from the new addons
            $listingAddon->update($newAddons[$listingAddon->id]);
            unset($newAddons[$listingAddon->id]);
        }

        // Any remaining items in the new addons are new addons that need to be added
        $this->listingCreateService->createListingAddons($listing, $newAddons);
    }

    private function filterBeds($roomCategory): array
    {
        // Filter out all the -1 in type of beds associative array
        return array_filter($roomCategory['type_of_beds'], function ($value) {
            return $value !== -1;
        });
    }
}
