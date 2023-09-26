<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingView;
use App\Models\User;

class ListingViewService
{
    private Listing $listing;
    private ?User $user;

    public function __construct(Listing $listing, ?User $user = null)
    {
        $this->listing = $listing;
        $this->user = $user;
    }

    public function addView(): void
    {
        $lastListingView = $this->getLastListingView();
        if (!$this->shouldRecordView($lastListingView)) {
            return;
        }

        $this->recordView([
            'user_id' => $this->user?->id
        ]);
        $this->listing->increment('views');
    }

    public function getLastListingView(): ?ListingView
    {
        $query = is_null($this->user)
            ? $this->listing->anonymousViews()
            : $this->listing->views()->where('user_id', $this->user->id);

        return $query->orderBy('created_at', 'desc')->first();
    }

    private function shouldRecordView(?ListingView $lastListingView = null): bool
    {
        if (is_null($lastListingView)) {
            return true;
        }

        // Prevents multiple views from the same user in a short period of time
        return now()->diffInSeconds($lastListingView['created_at']) > 1;
    }

    private function recordView(?array $attributes = []): void
    {
        $this->listing->views()->create($attributes);
    }
}
