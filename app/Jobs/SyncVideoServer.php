<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Video;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncVideoServer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Video $video;

    protected User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, User $user)
    {
        $this->video = $video;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = new Client;
        $videoServerUrl = config('services.video_server.url');

        if (! $videoServerUrl) {
            Log::error('Video server URL is not set');

            return;
        }

        try {
            $videoFile = Storage::disk('public')->get($this->video->file_path);

            $client->request('POST', $videoServerUrl, [
                'multipart' => [
                    [
                        'name' => 'video',
                        'contents' => $videoFile,
                        'filename' => $this->video->filename,
                    ],
                    [
                        'name' => 'video_id',
                        'contents' => $this->video->id,
                    ],
                    [
                        'name' => 'uploaded_by',
                        'contents' => $this->user->id,
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::error('Failed to sync video to the video server: '.$e->getMessage());
        }
    }
}
