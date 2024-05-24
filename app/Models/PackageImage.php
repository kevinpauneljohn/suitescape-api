<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PackageImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'package_id',
        'filename',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        //        route('api.images.get', ['id' => $this->id], false);
        return Storage::url('packages/'.$this->package_id.'/images/'.$this->filename);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
