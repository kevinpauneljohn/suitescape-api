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
        return [
            'id' => $this->id,
            'fullname' => $this->fullName(),
            'firstname' => $this->firstname,
            'middlename' => $this->middlename,
            'lastname' => $this->lastname,
            'gender' => $this->gender,
            'email' => $this->email,
            'address' => $this->address,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'region' => $this->region,
            'mobile_number' => $this->mobile_number,
            'date_of_birth' => $this->date_of_birth,
        ];
    }
}
