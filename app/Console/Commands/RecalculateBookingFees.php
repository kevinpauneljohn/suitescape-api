<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Constant;
use Illuminate\Console\Command;

class RecalculateBookingFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:recalculate-fees {--listing= : Recalculate for a specific listing ID} {--all : Recalculate all completed bookings} {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate suitescape fees and host earnings for completed bookings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $listingId = $this->option('listing');
        $all = $this->option('all');

        if (!$listingId && !$all) {
            $this->error('Please specify either --listing=<id> or --all');
            return 1;
        }

        // Get default fee from constants
        $defaultFee = 0;
        try {
            $feeConstant = Constant::where('key', 'suitescape_fee')->first();
            if ($feeConstant) {
                $defaultFee = (float) $feeConstant->value;
            }
        } catch (\Exception $e) {
            $this->warn("Could not retrieve default fee: " . $e->getMessage());
        }

        $this->info("Default Suitescape fee: ₱" . number_format($defaultFee, 2));

        // Query completed bookings
        $query = Booking::with('listing')
            ->where('status', 'completed');

        if ($listingId) {
            $query->where('listing_id', $listingId);
        }

        $bookings = $query->get();

        if ($bookings->isEmpty()) {
            $this->info('No completed bookings found.');
            return 0;
        }

        $this->info("Found {$bookings->count()} completed booking(s)");
        
        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }

        $updated = 0;
        $unchanged = 0;

        $this->newLine();
        $this->table(
            ['Booking ID', 'Listing', 'Amount', 'Old Fee', 'New Fee', 'Old Earnings', 'New Earnings', 'Change'],
            $bookings->map(function ($booking) use ($defaultFee, $dryRun, &$updated, &$unchanged) {
                // Determine the applicable fee
                $customFee = $booking->listing->custom_suitescape_fee ?? null;
                $newFee = $customFee !== null ? (float) $customFee : $defaultFee;
                $newEarnings = max(0, $booking->amount - $newFee);

                $oldFee = (float) ($booking->suitescape_fee ?? 0);
                $oldEarnings = (float) ($booking->host_earnings ?? $booking->amount);

                $hasChange = abs($newFee - $oldFee) > 0.001 || abs($newEarnings - $oldEarnings) > 0.001;

                if ($hasChange) {
                    $updated++;
                    
                    if (!$dryRun) {
                        $booking->update([
                            'suitescape_fee' => $newFee,
                            'host_earnings' => $newEarnings,
                        ]);
                    }
                } else {
                    $unchanged++;
                }

                $feeType = $customFee !== null ? '(custom)' : '(default)';
                $listingName = $booking->listing->name ?? 'Unknown';
                if (strlen($listingName) > 20) {
                    $listingName = substr($listingName, 0, 17) . '...';
                }

                return [
                    substr($booking->id, 0, 8) . '...',
                    $listingName,
                    '₱' . number_format($booking->amount, 2),
                    '₱' . number_format($oldFee, 2),
                    '₱' . number_format($newFee, 2) . ' ' . $feeType,
                    '₱' . number_format($oldEarnings, 2),
                    '₱' . number_format($newEarnings, 2),
                    $hasChange ? '✓ Updated' : '- No change',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info("Summary: {$updated} updated, {$unchanged} unchanged");

        if ($dryRun && $updated > 0) {
            $this->warn("Run without --dry-run to apply these changes");
        }

        return 0;
    }
}
