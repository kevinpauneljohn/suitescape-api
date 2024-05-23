<?php

namespace App\Http\Controllers\API;

use App\Events\ActiveStatusUpdated;
use App\Events\ProfileUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdateActiveStatusRequest;
use App\Http\Resources\ListingMetricResource;
use App\Http\Resources\UserResource;
use App\Services\FileNameService;
use App\Services\ProfileRetrievalService;
use App\Services\ProfileUpdateService;

class ProfileController extends Controller
{
    private ProfileRetrievalService $profileRetrievalService;

    private ProfileUpdateService $profileUpdateService;

    private FileNameService $fileNameService;

    public function __construct(ProfileRetrievalService $profileRetrievalService, ProfileUpdateService $profileUpdateService, FileNameService $fileNameService)
    {
        $this->middleware('auth:sanctum')->except('validateProfile');

        $this->profileRetrievalService = $profileRetrievalService;
        $this->profileUpdateService = $profileUpdateService;
        $this->fileNameService = $fileNameService;
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

        if ($request->hasFile('profile_image')) {
            // Generate a unique filename for the profile image
            $filename = $this->fileNameService->generateFileName($request->file('profile_image')->extension());

            // Store the profile image in the public disk
            $request->file('profile_image')->storeAs('avatars', $filename, 'public');

            // Set the filename to the validated data
            $validated['profile_image'] = $filename;
        }

        if ($request->hasFile('cover_image')) {
            // Generate a unique filename for the cover image
            $filename = $this->fileNameService->generateFileName($request->file('cover_image')->extension());

            // Store the cover image in the public disk
            $request->file('cover_image')->storeAs('covers', $filename, 'public');

            // Set the filename to the validated data
            $validated['cover_image'] = $filename;
        }

        $updated = $this->profileUpdateService->updateProfile($validated);

        if ($updated) {
            broadcast(new ProfileUpdated(auth()->user()));
        }

        return response()->json([
            'updated' => $updated,
            'message' => $updated ? 'Profile updated successfully' : 'No changes were made to the profile',
            'user' => new UserResource(auth()->user()),
        ]);
    }

    public function updatePassword(PasswordUpdateRequest $request)
    {
        $user = $this->profileUpdateService->updatePassword($request->validated()['new_password']);

        return response()->json([
            'message' => 'Password updated successfully',
            'user' => new UserResource($user),
        ]);
    }

    public function updateActiveSession(UpdateActiveStatusRequest $request)
    {
        $user = auth()->user();
        $deviceId = $request->validated()['device_id'];
        $deviceName = $request->validated()['device_name'];
        $active = $request->validated()['active'];

        // If the active status is true, create an active session
        if ($active) {
            $activeSession = $user->createActiveSession($deviceId, $deviceName);

            broadcast(new ActiveStatusUpdated($user, true));

            return response()->json([
                'message' => 'Active session created successfully',
                'active_session' => $activeSession,
                'user' => new UserResource($user),
            ]);
        }

        // If the active status is false, delete the active session
        $deleted = $user->deleteActiveSession($deviceId);

        if ($deleted) {
            broadcast(new ActiveStatusUpdated($user, false));

            return response()->json([
                'message' => 'Active session deleted successfully',
                'user' => new UserResource($user),
            ]);
        }

        // If no active session was found, return a message
        return response()->json([
            'message' => 'No active session found for this user',
            'user' => new UserResource($user),
        ]);
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
