<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory, HasUuids;

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function unreadMessages()
    {
        return $this->hasMany(Message::class)->where('receiver_id', auth()->id())->where('read_at', null);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    public function scopeOrderByLatestMessage($query)
    {
        return $query->addSelect(['latest_message' => Message::select('created_at')
            ->whereColumn('chat_id', 'chats.id')
            ->orderBy('created_at', 'desc')
            ->limit(1),
        ])->orderBy('latest_message', 'desc');
    }
}
