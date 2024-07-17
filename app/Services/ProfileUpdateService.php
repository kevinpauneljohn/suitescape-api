<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

class ProfileUpdateService
{
    public function updateProfile($userData): bool
    {
        $user = auth()->user();

        // Get a copy of the original attributes before the update
        //        $originalAttributes = $user->getOriginal();

        // Update the user
        //        $user->update($userData);

        // Get a copy of the updated original attributes
        //        $updatedOriginalAttributes = $user->getOriginal();

        // Check if any fields were changed
        //        $fieldsChanged = $this->checkIfFieldsChanged($originalAttributes, $updatedOriginalAttributes);

        //        return boolval($fieldsChanged);

        return $user->update($userData);
    }

    public function updatePassword($newPassword)
    {
        $user = auth()->user();

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        return $user;
    }

//    private function checkIfFieldsChanged($originalAttributes, $updatedOriginalAttributes)
//    {
//        return array_diff($updatedOriginalAttributes, $originalAttributes);
//    }
}
