<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'action_id' => $this->action_id,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
