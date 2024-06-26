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
        'is_consumable',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::created(function ($addon) {
            if (! $addon->is_consumable) {
                self::removeQuantity($addon);
            }
        });

        static::updated(function ($addon) {
            if (! $addon->is_consumable) {
                self::removeQuantity($addon);
            }
        });
    }

    private static function removeQuantity($addon)
    {
        $addon->updateQuietly([
            'quantity' => null,
        ]);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function scopeExcludeNoStocks($query)
    {
        return $query->where('quantity', '>', 0)->orWhereNull('quantity');
    }
}
