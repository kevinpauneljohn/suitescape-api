<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'listing_id',
        'filename',
        'privacy',
    ];

    public function getUrlAttribute()
    {
        //        return route('api.videos.get', ['id' => $this->id], false);
        return Storage::url('videos/'.$this->filename);
    }

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

    public function scopeOrderByDesc($query)
    {
        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');
    }

    public function scopeOrderByLowestPrice($query)
    {
        return $query->addSelect(['min_price' => RoomCategory::select('price')
            ->whereColumn('listing_id', 'videos.listing_id')
            ->orderBy('price')
            ->limit(1),
        ])->orderBy('min_price', 'asc');
    }
}
