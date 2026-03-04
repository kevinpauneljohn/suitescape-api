<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $reference_number;

    public string $message;

    public ?string $user_id;

    /**
     * Create a new event instance.
     */
    public function __construct(string $reference_number, string $message, ?string $user_id = null)
    {
        $this->reference_number = $reference_number;
        $this->message = $message;
        $this->user_id = $user_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('private-payment.'.$this->reference_number),
        ];

        // Also broadcast to user's payment channel if user_id is provided
        if ($this->user_id) {
            $channels[] = new PrivateChannel('private-payment.'.$this->user_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.failed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reference_number' => $this->reference_number,
            'message' => $this->message,
        ];
    }
}
