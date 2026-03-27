<?php

namespace App\Jobs;

use App\Events\VideoTranscodingComplete;
use App\Events\VideoTranscodingProgress;
use App\Jobs\GenerateSectionThumbnail;
use App\Jobs\SyncVideoServer;
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

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour for long video transcoding

    protected Video $video;

    protected string $directory;

    protected string $filename;

    protected string $tempPath;

    private const FILE_EXTENSION = '.mp4';

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, string $directory, string $filename, string $tempPath)
    {
        $this->video = $video;
        $this->directory = $directory;
        $this->filename = $filename . self::FILE_EXTENSION; // Append the file extension
        $this->tempPath = $tempPath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if video still exists (it might have been deleted before job ran)
        if (!$this->video || !$this->video->exists) {
            Log::warning("TranscodeVideo job - Video no longer exists. Skipping job for video ID: " . ($this->video->id ?? 'unknown'));
            return;
        }

        // Check if temp file exists
        if (!Storage::disk('public')->exists($this->tempPath)) {
            Log::error("TranscodeVideo job - Temp file not found: {$this->tempPath}");
            return;
        }

        // Log the temp file size for debugging
        $tempFileSize = Storage::disk('public')->size($this->tempPath);
        Log::info("TranscodeVideo job - Starting transcoding for video ID: {$this->video->id}, temp file size: " . number_format($tempFileSize / 1024 / 1024, 2) . " MB");

        // Create X264 format with settings for maximum device compatibility
        $format = new X264('aac', 'libx264');
        $format->setKiloBitrate(1500);
        $format->setAudioKiloBitrate(128);
        $format->setPasses(1); // Use single-pass encoding (more reliable, avoids duplicate MOOV issues)
        
        // Force 8-bit color (yuv420p) and Main profile for maximum compatibility
        // High 10 profile (10-bit) causes black screen on many Android devices
        $format->setAdditionalParameters([
            '-pix_fmt', 'yuv420p',       // Force 8-bit color
            '-profile:v', 'main',         // Use Main profile (most compatible)
            '-level:v', '4.0',            // H.264 Level 4.0 (supports 1080p)
            '-movflags', '+faststart',    // Enable fast start for web streaming
            '-preset', 'fast',            // Faster encoding with good quality
        ]);

        try {
            FFMpeg::fromDisk('public')
                ->open($this->tempPath)
                ->export()
                ->inFormat($format)
                ->resize(1080, 1920)
                ->onProgress(function ($percentage, $remaining, $rate) {
                    Log::info("Transcoding video: $percentage% done");
                    broadcast(new VideoTranscodingProgress($this->video, $percentage, $remaining, $rate));
                })
                ->toDisk('public')
                ->save($this->directory.'/'.$this->filename);

            Log::info("TranscodeVideo job - Transcoding completed successfully for video ID: {$this->video->id}");
        } catch (\Exception $e) {
            Log::error("TranscodeVideo job - FFmpeg failed for video ID: {$this->video->id}. Error: " . $e->getMessage());
            throw $e; // Re-throw to mark job as failed
        }

        // Verify output file exists and has content
        $outputPath = $this->directory.'/'.$this->filename;
        if (!Storage::disk('public')->exists($outputPath)) {
            Log::error("TranscodeVideo job - Output file not created: {$outputPath}");
            throw new \Exception("Transcoding failed: output file not created");
        }

        $outputSize = Storage::disk('public')->size($outputPath);
        Log::info("TranscodeVideo job - Output file size: " . number_format($outputSize / 1024 / 1024, 2) . " MB");

        if ($outputSize < 1000) { // Less than 1KB means something went wrong
            Log::error("TranscodeVideo job - Output file too small ({$outputSize} bytes), likely corrupted");
            Storage::disk('public')->delete($outputPath);
            throw new \Exception("Transcoding failed: output file too small");
        }

        // Update the video's transcoded status
        $this->video->update([
            'filename' => $this->filename,
            'is_transcoded' => true,
            'is_approved' => true,
        ]);

        // Delete the temp video
        Storage::disk('public')->delete($this->tempPath);

        // Load the listing to get the user for SyncVideoServer
        $this->video->load(['sections', 'listing.user']);

        // Broadcast transcoding complete to all users viewing this listing
        broadcast(new VideoTranscodingComplete($this->video));

        // Generate thumbnails for all sections of this video
        foreach ($this->video->sections as $section) {
            GenerateSectionThumbnail::dispatch($section);
        }

        // Now sync with video server (after transcoding is complete)
        if ($this->video->listing && $this->video->listing->user) {
            SyncVideoServer::dispatch($this->video, $this->video->listing->user);
        }
    }
}
