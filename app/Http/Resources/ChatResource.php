<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
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
            //            'user' => new UserResource($this->whenLoaded('users', fn () => $this->users->except(auth()->id())->first())),
            'user' => new UserResource($this->whenLoaded('users', fn () => $this->users->first())),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'unread_messages_count' => $this->whenCounted('unreadMessages'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
