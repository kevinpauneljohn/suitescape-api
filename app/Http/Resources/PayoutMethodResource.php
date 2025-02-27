<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutMethodResource extends JsonResource
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
            'user_id' => $this->user_id,
            'is_default' => boolval($this->is_default),
            'status' => $this->status,
            'type' => $this->getAccountType(),
            'account' => $this->whenLoaded('payoutable', fn() => $this->getAccountResource()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the account type based on the payoutable type.
     *
     * @return string
     */
    private function getAccountType(): string
    {
        return match (class_basename($this->payoutable_type)) {
            'GcashAccount' => 'GCash',
            'PaypalAccount' => 'PayPal',
            'BankAccount' => 'Bank',
            default => '',
        };
    }

    /**
     * Get the account resource based on the payoutable type.
     *
     * @return mixed
     */
    private function getAccountResource(): mixed
    {
        return match (class_basename($this->payoutable_type)) {
            'GcashAccount' => new GcashAccountResource($this->payoutable),
            'PaypalAccount' => new PaypalAccountResource($this->payoutable),
            'BankAccount' => new BankAccountResource($this->payoutable),
            default => null,
        };
    }
}
