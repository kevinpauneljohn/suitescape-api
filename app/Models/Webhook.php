<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = [
        'hook_id',
        'type',
        'disabled_reason',
        'events',
        'livemode',
        'status',
        'url',
        'secret_key',
        'paymongo_created_at',
        'paymongo_updated_at',
    ];

    protected $casts = [
        'events' => 'array',
        'livemode' => 'boolean',
        'paymongo_created_at' => 'datetime',
        'paymongo_updated_at' => 'datetime',
    ];
}
