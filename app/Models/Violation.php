<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    // Allow mass assignment for the 'name' field
    protected $fillable = ['name'];

    public function videos()
    {
        return $this->belongsToMany(Video::class, 'video_violations', 'violation_id', 'video_id');
    }
}
