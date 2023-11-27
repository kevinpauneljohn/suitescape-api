<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getProfile()
    {
        $user = auth()->user();

        return new UserResource($user);
    }

    public function updateProfile(ProfileUpdateRequest $request)
    {
        $user = auth()->user();

        // Get a copy of the original attributes before the update
        $originalAttributes = $user->getOriginal();

        // Update the user
        $user->update($request->validated());

        // Get a copy of the updated original attributes
        $updatedOriginalAttributes = $user->getOriginal();

        // Check if any fields were changed
        $fieldsChanged = array_diff($updatedOriginalAttributes, $originalAttributes);

        $message = $fieldsChanged ? 'Profile updated successfully' : 'No changes were made to the profile';

        return response()->json([
            'user' => new UserResource($user),
            'message' => $message,
            'updated' => boolval($fieldsChanged),
        ]);
    }
}
