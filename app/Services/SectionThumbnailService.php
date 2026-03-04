<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class SectionThumbnailService
{
    /**
     * Generate thumbnail for a section at its timestamp
     */
    public function generateThumbnail(Section $section): ?string
    {
        $video = $section->video;
        
        if (!$video || !$video->is_transcoded) {
            return null;
        }

        $videoPath = $video->file_path;
        
        if (!Storage::disk('public')->exists($videoPath)) {
            return null;
        }

        // Convert milliseconds to seconds
        $seconds = $section->milliseconds / 1000;
        
        // Generate unique thumbnail filename
        $thumbnailDir = "listings/{$video->listing_id}/thumbnails";
        $thumbnailFilename = "section_{$section->id}_{$section->milliseconds}.jpg";
        $thumbnailPath = "{$thumbnailDir}/{$thumbnailFilename}";

        // Ensure the directory exists
        Storage::disk('public')->makeDirectory($thumbnailDir);

        try {
            // Extract frame at the specified timestamp
            FFMpeg::fromDisk('public')
                ->open($videoPath)
                ->getFrameFromSeconds($seconds)
                ->export()
                ->toDisk('public')
                ->save($thumbnailPath);

            // Update section with thumbnail path
            $section->update(['thumbnail' => $thumbnailPath]);

            return $thumbnailPath;
        } catch (\Exception $e) {
            \Log::error("Failed to generate thumbnail for section {$section->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate thumbnails for all sections of a video
     */
    public function generateThumbnailsForVideo(Video $video): array
    {
        $results = [];
        
        foreach ($video->sections as $section) {
            $results[$section->id] = $this->generateThumbnail($section);
        }

        return $results;
    }

    /**
     * Regenerate thumbnail for a section (e.g., after milliseconds change)
     */
    public function regenerateThumbnail(Section $section): ?string
    {
        // Delete old thumbnail if exists
        if ($section->thumbnail && Storage::disk('public')->exists($section->thumbnail)) {
            Storage::disk('public')->delete($section->thumbnail);
        }

        return $this->generateThumbnail($section);
    }
}
