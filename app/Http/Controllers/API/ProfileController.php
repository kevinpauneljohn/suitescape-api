<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\ListingMetricResource;
use App\Http\Resources\UserResource;
use App\Services\ProfileRetrievalService;
use App\Services\ProfileUpdateService;

class ProfileController extends Controller
{
    private ProfileRetrievalService $profileRetrievalService;

    private ProfileUpdateService $profileUpdateService;

    public function __construct(ProfileRetrievalService $profileRetrievalService, ProfileUpdateService $profileUpdateService)
    {
        $this->middleware('auth:sanctum');

        $this->profileRetrievalService = $profileRetrievalService;
        $this->profileUpdateService = $profileUpdateService;
    }

    public function getProfile()
    {
        return new UserResource($this->profileRetrievalService->getProfile());
    }

    public function updateProfile(ProfileUpdateRequest $request)
    {
        return $this->profileUpdateService->updateProfile($request->validated());
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
