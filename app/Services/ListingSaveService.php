<?php

namespace App\Services;

use App\Models\Listing;

class ListingSaveService
{
    private Listing $listing;
    private string $userId;

    public function __construct(Listing $listing)
    {
        $this->listing = $listing;
        $this->userId = auth()->id();
    }

    public function addSave()
    {
        $this->listing->saves()->create([
            'user_id' => $this->userId,
        ]);
    }

    public function removeSave()
    {
        $this->listing->saves()->where('user_id', $this->userId)->delete();
    }
}
