<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'listing_id',
        'filename',
        'privacy',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function isOwnedBy($user)
    {
        return $user->id === $this->listing()->user_id;
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function scopeDesc($query)
    {
        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }
}
