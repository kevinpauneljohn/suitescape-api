<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');
        $isCurrentUser = $user && $user->id === $this->id;

        return [
            'id' => $this->id,
            'fullname' => $this->full_name,
            'firstname' => $this->firstname,
            'middlename' => $this->middlename,
            'lastname' => $this->lastname,
            'gender' => $this->gender,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'address' => $this->address,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'region' => $this->region,
            'mobile_number' => $this->mobile_number,
            'date_of_birth' => $this->date_of_birth,
            'profile_image_url' => $this->profile_image_url,
            'cover_image_url' => $this->cover_image_url,
            'active' => $this->isActive(),

            $this->mergeWhen($isCurrentUser, [
                'is_current_user' => true,
            ]),
        ];
    }
}
