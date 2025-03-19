<?php

namespace App\Services;

use App\Jobs\SyncVideoServer;
use App\Jobs\TranscodeVideo;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\NearbyPlace;
use Exception;
use Illuminate\Http\UploadedFile;
use Log;

class ListingCreateService
{
    protected FileNameService $fileNameService;

    protected MediaUploadService $mediaUploadService;

    public function __construct(FileNameService $fileNameService, MediaUploadService $mediaUploadService)
    {
        $this->fileNameService = $fileNameService;
        $this->mediaUploadService = $mediaUploadService;
    }

    /**
     * @throws Exception
     */
    public function createListing(array $listingData)
    {
        // Add the authenticated user's ID to the listing data
        $listingData['user_id'] = auth()->id();

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

    public function createListingImage(string $listingId, array $imageData, UploadedFile $file)
    {
        $listing = Listing::findOrFail($listingId);

        // Upload the image to the storage
        $directory = 'listings/'.$listingId.'/images/';
        $filename = $this->mediaUploadService->upload($file, $directory);

        return $listing->images()->create([
            'filename' => $filename,
            'privacy' => $imageData['privacy'],
        ]);
    }

    public function createListingVideo(string $listingId, array $videoData, UploadedFile $file)
    {
        $listing = Listing::findOrFail($listingId);

        // Upload as temp video for transcoding
        $directory = 'listings/'.$listingId.'/videos';
        $tempData = $this->mediaUploadService->upload($file, $directory, true, true);

        $video = $listing->videos()->create([
            'filename' => $tempData['filename'].'.'.$file->extension(), // Add the original extension back for reference
            'privacy' => $videoData['privacy'],
        ]);

        if (isset($videoData['sections'])) {
            $this->createVideoSections($video, $videoData['sections']);
        }

        TranscodeVideo::dispatch($video, $directory, $tempData['filename'], $tempData['temp_path']);
        SyncVideoServer::dispatch($video, $listing->user);

        return $video;
    }

    /**
     * @throws Exception
     */
    public function createListingRoom($listing, $room): void
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

    public function createRoomRule($room, $roomRule): void
    {
        $room->roomRule()->create($roomRule);
    }

    /**
     * @throws Exception
     */
    public function createRoomAmenities($room, $amenities): void
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

    public function createListingNearbyPlaces($listing, $nearbyPlaces): void
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

    public function createListingAddons($listing, $addons): void
    {
        $listing->addons()->createMany($addons);
    }

    public function createVideoSections($video, $sections): void
    {
        foreach ($sections as $section) {
            $video->sections()->create($section);
        }
    }

    private function filterBeds($roomCategory): array
    {
        // Filter out all the -1 in type of beds associative array
        return array_filter($roomCategory['type_of_beds'], function ($value) {
            return $value !== -1;
        });
    }
}
