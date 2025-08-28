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
        'is_transcoded',
        'is_approved',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        // If the video is currently transcoding, return null
        if (! $this->is_transcoded) {
            return null;
        }

        //        return route('api.videos.get', ['id' => $this->id], false);
        return Storage::url($this->file_path);
    }

    public function getFilePathAttribute()
    {
        return $this->directory.'/'.$this->filename;
    }

    public function getDirectoryAttribute()
    {
        return 'listings/'.$this->listing_id.'/videos';
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

    public function scopeIsTranscoded($query, $isTranscoded = true)
    {
        return $query->where('is_transcoded', $isTranscoded);
    }

    public function scopeIsApproved($query, $isApproved = true)
    {
        if (is_null($isApproved)) {
            return $query->whereNull('is_approved');
        }

        return $query->where('is_approved', $isApproved);
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

    public function violations()
    {
        return $this->belongsToMany(Violation::class, 'video_violations', 'video_id', 'violation_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
