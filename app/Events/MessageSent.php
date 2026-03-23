<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public array $messageData;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        // Ensure sender is loaded
        if (!$message->relationLoaded('sender')) {
            $message->load('sender');
        }
        
        $sender = $message->sender;
        
        // Store the data immediately to avoid serialization issues
        $this->messageData = [
            'id' => $message->id,
            'content' => $message->content,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'created_at' => $message->created_at?->toISOString(),
            'sender' => $sender ? [
                'id' => $sender->id,
                'fullname' => $sender->full_name,
                'firstname' => $sender->firstname,
                'lastname' => $sender->lastname,
                'profile_image_url' => $sender->profile_image_url,
            ] : null,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->messageData['receiver_id']),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->messageData;
    }
}
