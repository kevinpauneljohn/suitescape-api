<?php

namespace App\Services;

use App\Events\ChatRead;
use App\Events\MessageSent;
use App\Models\Chat;
use App\Models\Message;
use App\Models\MessageResponseTime;
use App\Models\User;
use Blaspsoft\Blasp\Facades\Blasp;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ChatService
{
    private const SECONDS_PER_DAY = 86400; // 24 * 3600

    private const DAYS_TO_TRACK = 30;

    private const MAX_RESPONSE_DAYS = 7;

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

    public function searchChats(?string $searchQuery, ?int $limit = 20)
    {
        $user = auth()->user();

        return $user->chats()
            ->where(function ($query) use ($searchQuery) {
                $query->whereHas('messages', function ($subQuery) use ($searchQuery) {
                    $subQuery->whereRaw('MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchQuery]);
                })->orWhereHas('users', function ($subQuery) use ($searchQuery) {
                    $subQuery->whereRaw('MATCH(firstname, lastname, email) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchQuery]);
                });
            })->with([
                'users' => function ($query) use ($user) {
                    $query->where('users.id', '!=', $user->id);
                },
                'latestMessage',
            ])
            ->orderByLatestMessage()
            ->limit($limit)
            ->get();
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

    public function getMessages(string $senderId, string $receiverId, $cursor = null)
    {
        return Message::with('listing.images')
            ->where(function ($query) use ($senderId, $receiverId) {
                $query->where(function ($q) use ($senderId, $receiverId) {
                    $q->where('sender_id', $senderId)
                    ->where('receiver_id', $receiverId);
                })->orWhere(function ($q) use ($senderId, $receiverId) {
                    $q->where('sender_id', $receiverId)
                    ->where('receiver_id', $senderId);
                });
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->cursorPaginate(10, ['*'], 'cursor', $cursor);
    }

    /**
     * @throws Exception
     */
    public function sendMessage(string $senderId, string $receiverId, string $content, ?string $listingId = null)
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

        // Filter the message content from links, email, numbers, etc.
        $filteredContent = $this->filterMessageContent($content);

        // Get the last message with a listing
        $lastMessage = $chat->messages()->whereNotNull('listing_id')->latest()->first();

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            // Check if the listing id exists on the last message with listing
            'listing_id' => optional($lastMessage)->listing_id === $listingId ? null : $listingId,
            'content' => $filteredContent,
        ]);

        $this->trackResponseTime($chat, $message);

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

    private function filterMessageContent(string $content): string
    {
        // Filter emails
        $content = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[email removed]', $content);

        // Filter phone numbers (various formats)
        $content = preg_replace('/(?:\+?\d{1,3}[-.\s]?)?(?:\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4,6}/', '[phone removed]', $content);

        // Filter profanity
        $content = Blasp::check($content)->getCleanString();

        // Remove excess whitespace
        return trim(preg_replace('/\s+/', ' ', $content));
    }

    private function trackResponseTime(Chat $chat, Message $message): void
    {
        $lastMessage = Message::where('chat_id', $chat->id)
            ->where('sender_id', $message->receiver_id)
            ->where('created_at', '<', $message->created_at)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastMessage) {
            $responseTime = $message->created_at->diffInSeconds($lastMessage->created_at);

            // Only track responses within MAX_RESPONSE_DAYS
            if ($responseTime <= self::MAX_RESPONSE_DAYS * self::SECONDS_PER_DAY) {
                MessageResponseTime::create([
                    'chat_id' => $chat->id,
                    'message_id' => $message->id,
                    'user_id' => $message->sender_id,
                    'response_time_seconds' => $responseTime,
                ]);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function getHostResponseRate(string $userId): array
    {
        $startDate = Carbon::now()->subDays(self::DAYS_TO_TRACK);

        $responseTimes = MessageResponseTime::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->get();

        if ($responseTimes->isEmpty()) {
            return [
                'average_response_time' => null,
                'overall_response_rate' => null,
            ];
        }

        return [
            'average_response_time' => $this->formatAverageResponseTime($responseTimes),
            'overall_response_rate' => $this->calculateResponseRate($userId, $startDate, $responseTimes),
        ];
    }

    /**
     * @throws Exception
     */
    private function formatAverageResponseTime(Collection $responseTimes): string
    {
        $averageSeconds = $responseTimes->avg('response_time_seconds');

        if (! $averageSeconds) {
            return 'No responses yet';
        }

        return CarbonInterval::seconds($averageSeconds)
            ->cascade()
            ->forHumans(['short' => true, 'parts' => 1]);
    }

    private function calculateResponseRate(string $userId, Carbon $startDate, Collection $responseTimes): float
    {
        $totalMessages = Message::where('receiver_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->count();

        return $totalMessages > 0 ? ($responseTimes->count() / $totalMessages) * 100 : 0;
    }
}
