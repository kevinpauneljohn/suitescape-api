<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingView;
use App\Models\User;

class ListingViewService
{
    private Listing $listing;

    private ?string $userId;

    public function __construct(Listing $listing)
    {
        $this->listing = $listing;
        $this->userId = auth('sanctum')->id();
    }

    public function addView(): bool
    {
        $lastListingView = $this->getLastListingView();
        if (! $this->shouldRecordView($lastListingView)) {
            return false;
        }

        $this->recordView([
            'user_id' => $this->userId,
        ]);

        return true;
    }

    public function getLastListingView(): ?ListingView
    {
        $query = is_null($this->userId)
            ? $this->listing->anonymousViews()
            : $this->listing->views()->where('user_id', $this->userId);

        return $query->orderBy('created_at', 'desc')->first();
    }

    private function shouldRecordView(ListingView $lastListingView = null): bool
    {
        if (! $lastListingView) {
            return true;
        }

        // Prevents multiple views from the same user in a short period of time
        return now()->diffInMinutes($lastListingView['created_at']) > 5;
    }

    private function recordView(?array $attributes = []): void
    {
        $this->listing->views()->create($attributes);
    }
}
