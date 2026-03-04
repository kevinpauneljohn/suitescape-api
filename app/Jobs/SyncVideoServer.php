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
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 300; // 5 minutes

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = true;

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
        $videoServerUrl = config('services.video_server.url');

        if (! $videoServerUrl) {
            Log::info("Video server URL is not set. Skipping sync for video ID: {$this->video->id}");
            return;
        }

        // Check if video file exists
        if (!Storage::disk('public')->exists($this->video->file_path)) {
            Log::error("Video file not found for video ID: {$this->video->id}, path: {$this->video->file_path}");
            return;
        }

        try {
            $client = new Client([
                'timeout' => 300, // 5 minute timeout for the request (large files)
                'connect_timeout' => 10, // 10 second connection timeout
            ]);

            // Use stream instead of loading entire file into memory
            $videoPath = Storage::disk('public')->path($this->video->file_path);

            $client->request('POST', $videoServerUrl, [
                'multipart' => [
                    [
                        'name' => 'video',
                        'contents' => fopen($videoPath, 'r'),
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

            Log::info("Successfully synced video ID: {$this->video->id} to video server");
        } catch (GuzzleException $e) {
            Log::error('Failed to sync video to the video server: '.$e->getMessage());
            // Don't re-throw - just log the error and mark as complete
            // This prevents the queue from being blocked by unreachable servers
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SyncVideoServer job failed permanently for video ID: {$this->video->id}. Error: {$exception->getMessage()}");
    }
}
