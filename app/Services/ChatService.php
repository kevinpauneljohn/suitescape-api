<?php

namespace App\Services;

use App\Events\ChatRead;
use App\Events\MessageSent;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Exception;

class ChatService
{
    public function getAllChats()
    {
        $user = auth()->user();

        return $user->chats()->with([
            'users' => function ($query) use ($user) {
                $query->where('users.id', '!=', $user->id);
            },
            'latestMessage',
        ])->withCount('unreadMessages')->orderByLatestMessage()->get();
    }

    public function getChat(string $senderId, string $receiverId)
    {
        //        if ($senderId === $receiverId) {
        //            return Chat::whereHas('users', function ($query) use ($senderId) {
        //                $query->where('users.id', $senderId);
        //            })->havingRaw('COUNT(*) = 1')->first();
        //        }

        return Chat::whereHas('users', function ($query) use ($senderId) {
            $query->where('users.id', $senderId);
        })->whereHas('users', function ($query) use ($receiverId) {
            $query->where('users.id', $receiverId);
        })->first();
    }

    public function getMessages(string $senderId, string $receiverId)
    {
        return Message::where(function ($query) use ($senderId, $receiverId) {
            $query->where('sender_id', $senderId)
                ->where('receiver_id', $receiverId);
        })->orWhere(function ($query) use ($senderId, $receiverId) {
            $query->where('sender_id', $receiverId)
                ->where('receiver_id', $senderId);
        })->orderByDesc('created_at')->get();
    }

    /**
     * @throws Exception
     */
    public function sendMessage(string $senderId, string $receiverId, string $content)
    {
        // Check if sender and receiver are the same
        if ($senderId === $receiverId) {
            throw new Exception('You cannot send a message to yourself.', 400);
        }

        // Get the chat
        $chat = $this->getChat($senderId, $receiverId);

        // If chat does not exist, create a new one
        if (! $chat) {
            $chat = $this->startChat($senderId, $receiverId);
        }

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'content' => $content,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return $message;
    }

    public function markMessagesAsRead(string $chatId, string $userId): void
    {
        $chat = Chat::find($chatId);
        $user = User::find($userId);

        // Mark all unread messages as read
        $chat->unreadMessages()->where('receiver_id', $userId)->update(['read_at' => now()]);

        broadcast(new ChatRead($chat, $user))->toOthers();
    }

    public function startChat(string $senderId, string $receiverId)
    {
        $chat = Chat::create();

        // Add the users to the chat
        $chat->users()->attach([$senderId, $receiverId]);

        return $chat;
    }
}
