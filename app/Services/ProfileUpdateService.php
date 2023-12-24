<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

class ProfileUpdateService
{
    public function updateProfile($userData)
    {
        $user = auth()->user();

        // Get a copy of the original attributes before the update
        $originalAttributes = $user->getOriginal();

        // Update the user
        $user->update($userData);

        // Get a copy of the updated original attributes
        $updatedOriginalAttributes = $user->getOriginal();

        // Check if any fields were changed
        $fieldsChanged = $this->checkIfFieldsChanged($originalAttributes, $updatedOriginalAttributes);

        $message = $fieldsChanged ? 'Profile updated successfully' : 'No changes were made to the profile';

        return response()->json([
            'message' => $message,
            'updated' => boolval($fieldsChanged),
            'user' => new UserResource($user),
        ]);
    }

    public function updatePassword($newPassword)
    {
        $user = auth()->user();

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
            'user' => new UserResource($user),
        ]);
    }

    private function checkIfFieldsChanged($originalAttributes, $updatedOriginalAttributes)
    {
        return array_diff($updatedOriginalAttributes, $originalAttributes);
    }
}
