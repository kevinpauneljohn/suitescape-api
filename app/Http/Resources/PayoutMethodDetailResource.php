<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutMethodDetailResource extends JsonResource
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
            'payout_method_id' => $this->payout_method_id,
            'type' => $this->type,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'role' => $this->role,
            'bank_name' => $this->bank_name,
            'bank_type' => $this->bank_type,
            'swift_code' => $this->swift_code,
            'bank_code' => $this->bank_code,
            'email' => $this->email,
            'phone' => $this->phone,
            'dob' => $this->dob,
            'pob' => $this->pob,
            'citizenship' => $this->citizenship,
            'billing_country' => $this->billing_country,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
