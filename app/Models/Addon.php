<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'listing_id',
        'name',
        'price',
        'description',
        'quantity',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function scopeExcludeZeroQuantity($query)
    {
        return $query->where('quantity', '>', 0);
    }
}
