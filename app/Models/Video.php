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
        'is_transcoding',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        // If the video is currently transcoding, return null
        if ($this->is_transcoding) {
            return null;
        }

        //        return route('api.videos.get', ['id' => $this->id], false);
        return Storage::url('listings/'.$this->listing_id.'/videos/'.$this->filename);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function isOwnedBy($user)
    {
        return $user->id === $this->listing()->user_id;
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function scopePrivate($query)
    {
        return $query->where('privacy', 'private');
    }

    public function scopeIsTranscoding($query, $isTranscoding = true)
    {
        return $query->where('is_transcoding', $isTranscoding);
    }

    public function scopeOrderByDesc($query)
    {
        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }

    //    public function scopeOrderByLowestEntirePlacePrice($query)
    //    {
    //        $priceColumn = Listing::getCurrentPriceColumn();
    //
    //        return $query->orderBy($priceColumn, 'asc');
    //    }

    //    public function scopeOrderByLowestRoomPrice($query)
    //    {
    //        return $query->addSelect(['min_price' => RoomCategory::getMinPriceQuery($this->listing_id)])
    //            ->orderBy('min_price', 'asc');
    //    }
}
