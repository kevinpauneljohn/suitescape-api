<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            $this->mergeUnless($this->relationLoaded('user'), [
                'user_id' => $this->user_id,
            ]),
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => new CartItemCollection($this->whenLoaded('items')),
        ];
    }
}
