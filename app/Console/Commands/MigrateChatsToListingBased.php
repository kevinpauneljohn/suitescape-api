<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Console\Command;

class MigrateChatsToListingBased extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chats:migrate-to-listing-based {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing chats to listing-based conversations by splitting them per listing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        // Get all chats without a listing_id
        $chats = Chat::whereNull('listing_id')->get();
        
        $this->info("Found {$chats->count()} legacy chats to process");

        $totalNewChats = 0;
        $totalMessagesUpdated = 0;

        foreach ($chats as $chat) {
            $this->line("\nProcessing chat: {$chat->id}");
            
            // Get all unique listing IDs from messages in this chat
            $listingIds = Message::where('chat_id', $chat->id)
                ->whereNotNull('listing_id')
                ->distinct()
                ->pluck('listing_id')
                ->toArray();

            if (empty($listingIds)) {
                $this->warn("  No listings found in messages, skipping");
                continue;
            }

            $this->info("  Found " . count($listingIds) . " unique listings");
            
            // Get the chat users
            $userIds = $chat->users()->pluck('users.id')->toArray();

            foreach ($listingIds as $index => $listingId) {
                // For the first listing, update the existing chat
                if ($index === 0) {
                    if (!$dryRun) {
                        $chat->update(['listing_id' => $listingId]);
                    }
                    $this->line("  Updated original chat with listing_id: {$listingId}");
                } else {
                    // For additional listings, create new chats
                    if (!$dryRun) {
                        $newChat = Chat::create(['listing_id' => $listingId]);
                        $newChat->users()->attach($userIds);
                        
                        // Update messages for this listing to point to new chat
                        $updated = Message::where('chat_id', $chat->id)
                            ->where('listing_id', $listingId)
                            ->update(['chat_id' => $newChat->id]);
                        
                        $totalMessagesUpdated += $updated;
                        $this->line("  Created new chat {$newChat->id} for listing {$listingId}, moved {$updated} messages");
                    } else {
                        $messageCount = Message::where('chat_id', $chat->id)
                            ->where('listing_id', $listingId)
                            ->count();
                        $this->line("  Would create new chat for listing {$listingId}, would move {$messageCount} messages");
                    }
                    $totalNewChats++;
                }
            }

            // Handle messages without listing_id - associate with the first listing
            if (!$dryRun && count($listingIds) > 0) {
                $nullListingMessages = Message::where('chat_id', $chat->id)
                    ->whereNull('listing_id')
                    ->update(['listing_id' => $listingIds[0]]);
                
                if ($nullListingMessages > 0) {
                    $this->line("  Associated {$nullListingMessages} messages without listing to {$listingIds[0]}");
                }
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Processed: {$chats->count()} legacy chats");
        $this->info("  New chats created: {$totalNewChats}");
        $this->info("  Messages updated: {$totalMessagesUpdated}");
        
        if ($dryRun) {
            $this->warn("\nThis was a dry run. Run without --dry-run to apply changes.");
        } else {
            $this->info("\nMigration complete!");
        }

        return Command::SUCCESS;
    }
}
