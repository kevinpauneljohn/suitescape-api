<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');
        $isCurrentSender = $user && $this->sender_id === $user->id;

        return [
            'id' => $this->id,
            $this->mergeUnless($this->relationLoaded('chat'), [
                'chat_id' => $this->chat_id,
            ]),
            $this->mergeUnless($this->relationLoaded('sender'), [
                'sender_id' => $this->sender_id,
            ]),
            $this->mergeUnless($this->relationLoaded('receiver'), [
                'receiver_id' => $this->receiver_id,
            ]),
            $this->mergeUnless($this->relationLoaded('listing'), [
                'listing_id' => $this->listing_id,
            ]),
            'chat' => new ChatResource($this->whenLoaded('chat')),
            'sender' => new UserResource($this->whenLoaded('sender')),
            'receiver' => new UserResource($this->whenLoaded('receiver')),
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'content' => $this->content,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_current_user_sender' => $isCurrentSender,
        ];
    }
}
