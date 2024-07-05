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
            'is_transcoded' => $this->is_transcoded,
            'is_approved' => $this->is_approved,
            //            'listing_id' => $this->whenNotNull($this->whenLoaded('listing', null, $this->listing_id)),
            $this->mergeUnless($this->relationLoaded('listing'), ['listing_id' => $this->listing_id]),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'url' => $this->url,
        ];
    }
}
