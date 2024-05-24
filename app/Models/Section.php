<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'label',
        'milliseconds',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
