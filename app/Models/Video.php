<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory, HasUuids;

    public $fillable = [
        'user_id',
        'filename',
        'title',
        'description',
        'privacy',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
