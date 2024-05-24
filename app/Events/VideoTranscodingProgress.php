<?php

namespace App\Events;

use App\Http\Resources\VideoResource;
use App\Models\Video;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoTranscodingProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Video $video;

    public float $percentage;

    public float $remaining;

    public float $rate;

    /**
     * Create a new event instance.
     */
    public function __construct($video, $percentage, $remaining, $rate)
    {
        $this->video = $video;
        $this->percentage = $percentage;
        $this->remaining = $remaining;
        $this->rate = $rate;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-video-transcoding.'.$this->video->id),
            new PrivateChannel('private-video-transcoding.'.$this->video->listing_id),
            new PrivateChannel('private-video-transcoding.'.$this->video->listing->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'video-transcoding.progress';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'video' => (new VideoResource($this->video))->resolve(),
            'percentage' => $this->percentage,
            'remaining' => $this->remaining,
            'rate' => $this->rate,
        ];
    }
}
