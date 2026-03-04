<?php

namespace App\Console\Commands;

use App\Models\Section;
use App\Services\SectionThumbnailService;
use Illuminate\Console\Command;

class GenerateSectionThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sections:generate-thumbnails {--video= : Generate for specific video ID} {--force : Regenerate existing thumbnails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate thumbnails for video sections';

    /**
     * Execute the console command.
     */
    public function handle(SectionThumbnailService $thumbnailService)
    {
        $videoId = $this->option('video');
        $force = $this->option('force');

        $query = Section::with('video')
            ->whereHas('video', function ($q) {
                $q->where('is_transcoded', true);
            });

        if ($videoId) {
            $query->where('video_id', $videoId);
        }

        if (!$force) {
            $query->whereNull('thumbnail');
        }

        $sections = $query->get();

        if ($sections->isEmpty()) {
            $this->info('No sections found to process.');
            return 0;
        }

        $this->info("Processing {$sections->count()} sections...");
        $bar = $this->output->createProgressBar($sections->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($sections as $section) {
            $result = $force 
                ? $thumbnailService->regenerateThumbnail($section)
                : $thumbnailService->generateThumbnail($section);

            if ($result) {
                $success++;
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Completed! Success: {$success}, Failed: {$failed}");

        return 0;
    }
}
