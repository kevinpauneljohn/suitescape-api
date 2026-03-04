<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'label',
        'milliseconds',
        'thumbnail',
    ];

    protected $appends = ['thumbnail_url'];

    public function getThumbnailUrlAttribute()
    {
        if (!$this->thumbnail) {
            return null;
        }
        return Storage::url($this->thumbnail);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
