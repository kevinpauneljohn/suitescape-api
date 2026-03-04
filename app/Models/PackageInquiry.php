<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageInquiry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'package_id',
        'user_id',
        'name',
        'email',
        'phone',
        'message',
        'status',
        'admin_notes',
        'read_at',
        'replied_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markAsReplied()
    {
        $this->update([
            'status' => 'replied',
            'replied_at' => now(),
        ]);
    }
}
