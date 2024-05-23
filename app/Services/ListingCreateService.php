<?php

namespace App\Services;

use App\Jobs\TranscodeVideo;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\NearbyPlace;
use Exception;
use Log;

class ListingCreateService
{
    protected FileNameService $fileNameService;

    public function __construct(FileNameService $fileNameService)
    {
        $this->fileNameService = $fileNameService;
    }

    /**
     * @throws Exception
     */
    public function createListing(array $listingData)
    {
        // Add the authenticated user's ID to the listing data
        $listingData['user_id'] = auth('sanctum')->id();

        // Create a new listing
        $listing = Listing::create($listingData);

        if (isset($listingData['rooms'])) {
            foreach ($listingData['rooms'] as $room) {
                $this->createListingRoom($listing, $room);
            }
        }

        if (isset($listingData['images'])) {
            foreach ($listingData['images'] as $imageData) {
                $this->createListingImage($listing->id, $imageData, $imageData['file']);
            }
        }

        if (isset($listingData['videos'])) {
            foreach ($listingData['videos'] as $videoData) {
                $this->createListingVideo($listing->id, $videoData, $videoData['file']);
            }
        }

        if (isset($listingData['addons'])) {
            $this->createListingAddons($listing, $listingData['addons']);
        }

        if (isset($listingData['nearby_places'])) {
            $this->createListingNearbyPlaces($listing, $listingData['nearby_places']);
        }

        return $listing;
    }

    public function createListingImage(string $listingId, array $imageData, $file)
    {
        $listing = Listing::findOrFail($listingId);

        // Upload the image to the storage
        $filename = $this->fileNameService->generateFileName($file->extension());
        $directory = 'listings/'.$listingId.'/images/';
        $file->storeAs($directory, $filename, 'public');

        return $listing->images()->create([
            'user_id' => auth('sanctum')->user()->id,
            'filename' => $filename,
            'privacy' => $imageData['privacy'],
        ]);
    }

    public function createListingVideo(string $listingId, array $videoData, $file)
    {
        $listing = Listing::findOrFail($listingId);

        // Upload temp video to the storage
        $filename = $this->fileNameService->generateFileName($file->extension());
        $directory = 'listings/'.$listingId.'/videos';
        $tempFilename = 'temp_'.$filename;
        $tempPath = $file->storeAs($directory, $tempFilename, 'public');

        $video = $listing->videos()->create([
            'user_id' => auth('sanctum')->user()->id,
            'filename' => $tempFilename,
            'privacy' => $videoData['privacy'],
            'is_transcoding' => true,
        ]);

        TranscodeVideo::dispatch($video, $tempPath, $directory, $filename);

        if (isset($videoData['sections'])) {
            $this->createVideoSections($video, $videoData['sections']);
        }

        return $video;
    }

    /**
     * @throws Exception
     */
    public function createListingRoom($listing, $room)
    {
        if (! isset($room['category'])) {
            throw new Exception('Room category is required.');
        }

        $roomCategory = $this->createRoomCategory($listing, $room['category']);

        $listingRoom = $listing->rooms()->create([
            'room_category_id' => $roomCategory->id,
        ]);

        if (isset($room['rule'])) {
            $this->createRoomRule($listingRoom, $room['rule']);
        }

        if (isset($room['amenities'])) {
            $this->createRoomAmenities($listingRoom, $room['amenities']);
        }
    }

    /**
     * @throws Exception
     */
    public function createRoomCategory($listing, $roomCategory)
    {
        $roomCategory['type_of_beds'] = $this->filterBeds($roomCategory);

        return $listing->roomCategories()->create($roomCategory);
    }

    public function createRoomRule($room, $roomRule)
    {
        $room->roomRule()->create($roomRule);
    }

    /**
     * @throws Exception
     */
    public function createRoomAmenities($room, $amenities)
    {
        $roomAmenities = array_keys(array_filter($amenities));

        foreach ($roomAmenities as $roomAmenity) {
            $amenity = Amenity::where('name', $roomAmenity)->first();

            if (! $amenity) {
                Log::error("Amenity $roomAmenity not found.");

                continue;
            }

            $room->roomAmenities()->create([
                'amenity_id' => $amenity->id,
            ]);
        }
    }

    public function createListingNearbyPlaces($listing, $nearbyPlaces)
    {
        $listingNearbyPlaces = array_keys(array_filter($nearbyPlaces));

        foreach ($listingNearbyPlaces as $listingNearbyPlace) {
            $nearbyPlace = NearbyPlace::where('name', $listingNearbyPlace)->first();

            if (! $nearbyPlace) {
                Log::error("Nearby place $listingNearbyPlace not found.");

                continue;
            }

            $listing->listingNearbyPlaces()->create([
                'nearby_place_id' => $nearbyPlace->id,
            ]);
        }
    }

    public function createListingAddons($listing, $addons)
    {
        $listing->addons()->createMany($addons);
    }

    public function createVideoSections($video, $sections)
    {
        foreach ($sections as $section) {
            $video->sections()->create($section);
        }
    }

    private function filterBeds($roomCategory)
    {
        // Filter out all the -1 in type of beds associative array
        return array_filter($roomCategory['type_of_beds'], function ($value) {
            return $value !== -1;
        });
    }
}
