<?php

namespace App\Jobs;

use App\Events\VideoTranscodingProgress;
use App\Models\Video;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Video $video;

    protected string $tempPath;

    protected string $directory;

    protected string $filename;

    /**
     * Create a new job instance.
     */
    public function __construct($video, $tempPath, $directory, $filename)
    {
        $this->video = $video;
        $this->tempPath = $tempPath;
        $this->directory = $directory;
        $this->filename = $filename;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        FFMpeg::fromDisk('public')
            ->open($this->tempPath)
            ->export()
            ->inFormat(new X264)
            ->resize(1080, 1920)
            ->onProgress(function ($percentage, $remaining, $rate) {
                Log::info("Transcoding video: $percentage% done");
                broadcast(new VideoTranscodingProgress($this->video, $percentage, $remaining, $rate));
            })
            ->toDisk('public')
            ->save($this->directory.'/'.$this->filename);

        // Delete the temp video
        Storage::disk('public')->delete($this->tempPath);

        // Update the video's transcoded status
        $this->video->update([
            'filename' => $this->filename,
            'is_transcoding' => false,
        ]);
    }
}
