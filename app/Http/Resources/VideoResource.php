<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
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
            'filename' => $this->filename,
            'privacy' => $this->privacy,
            //            'listing_id' => $this->whenNotNull($this->whenLoaded('listing', null, $this->listing_id)),
            $this->mergeUnless($this->relationLoaded('listing'), ['listing_id' => $this->listing_id]),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            //            'url' => route('api.videos.get', $this->id, false),
            'url' => '/videos/'.$this->id,
        ];
    }
}
