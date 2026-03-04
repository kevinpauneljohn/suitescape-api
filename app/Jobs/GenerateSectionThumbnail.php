<?php

namespace App\Jobs;

use App\Models\Section;
use App\Services\SectionThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSectionThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Section $section;

    /**
     * Create a new job instance.
     */
    public function __construct(Section $section)
    {
        $this->section = $section;
    }

    /**
     * Execute the job.
     */
    public function handle(SectionThumbnailService $thumbnailService): void
    {
        $thumbnailService->generateThumbnail($this->section);
    }
}
