<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomCategoryResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'floor_area' => $this->floor_area,
            'type_of_beds' => $this->type_of_beds,
            'pax' => $this->pax,
            'price' => floatval($this->price),
            'weekday_price' => floatval($this->weekday_price),
            'weekend_price' => floatval($this->weekend_price),
            'quantity' => $this->quantity,
        ];
    }
}
