<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\ListingMetricResource;
use App\Http\Resources\UserResource;
use App\Services\ImageUploadService;
use App\Services\ProfileRetrievalService;
use App\Services\ProfileUpdateService;

class ProfileController extends Controller
{
    private ProfileRetrievalService $profileRetrievalService;

    private ProfileUpdateService $profileUpdateService;

    private ImageUploadService $imageUploadService;

    public function __construct(ProfileRetrievalService $profileRetrievalService, ProfileUpdateService $profileUpdateService, ImageUploadService $imageUploadService)
    {
        $this->middleware('auth:sanctum')->except('validateProfile');

        $this->profileRetrievalService = $profileRetrievalService;
        $this->profileUpdateService = $profileUpdateService;
        $this->imageUploadService = $imageUploadService;
    }

    public function getProfile()
    {
        return new UserResource($this->profileRetrievalService->getProfile());
    }

    public function validateProfile(ProfileUpdateRequest $request)
    {
        return $request->validated();
    }

    public function updateProfile(ProfileUpdateRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('picture')) {
            // Upload the image and get the filename
            $filename = $this->imageUploadService->upload($request->file('picture'));

            // Set the filename to the validated data
            $validated['picture'] = $filename;
        }

        return $this->profileUpdateService->updateProfile($validated);
    }

    public function updatePassword(PasswordUpdateRequest $request)
    {
        return $this->profileUpdateService->updatePassword($request->validated()['new_password']);
    }

    public function getLikedListings()
    {
        return ListingMetricResource::collection($this->profileRetrievalService->getLikedListings());
    }

    public function getSavedListings()
    {
        return ListingMetricResource::collection($this->profileRetrievalService->getSavedListings());
    }

    public function getViewedListings()
    {
        return ListingMetricResource::collection($this->profileRetrievalService->getViewedListings());
    }
}
