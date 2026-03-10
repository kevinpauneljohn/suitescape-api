<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShuffleVideoOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:shuffle-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Shuffle the random_order column for all videos to randomize the feed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Shuffling video order...');

        DB::statement('UPDATE videos SET random_order = FLOOR(RAND() * 1000000000)');

        $count = DB::table('videos')->count();
        $this->info("Successfully shuffled order for {$count} videos.");

        return Command::SUCCESS;
    }
}
