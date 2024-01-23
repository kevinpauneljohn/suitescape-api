<?php

namespace App\Http\Resources;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CartItemCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($item) {
            // Throw an exception if the necessary relationships are not loaded
            throw_if(! $item->relationLoaded('room') ||
                ! $item->room->relationLoaded('listing') ||
                ! $item->room->listing->relationLoaded('host'),
                new Exception('Required relationships not loaded'));

            return $item;
        })->groupBy('room.listing.host.id')->map(function ($items) {
            // Group the items by host
            return [
                'host' => new HostResource($items->first()->room->listing->host),
                'data' => CartItemResource::collection($items),
            ];
        })->values()->toArray();
    }
}
