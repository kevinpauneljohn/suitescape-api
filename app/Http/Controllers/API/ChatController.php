<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatController extends Controller
{
    private ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->middleware('auth:sanctum');

        $this->chatService = $chatService;
    }

    /**
     * Get All Chats
     *
     * Retrieves a collection of all chats for the authenticated user.
     * Optionally filter by mode: 'host' (conversations about your listings) or 'guest' (conversations about others' listings)
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllChats()
    {
        $mode = request('mode'); // 'host', 'guest', or null for all
        return ChatResource::collection($this->chatService->getAllChats($mode));
    }

    /**
     * Search Chats
     *
     * Retrieves a collection of chats for the authenticated user that match the specified search term.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function searchChats(SearchRequest $request)
    {
        return ChatResource::collection($this->chatService->searchChats(
            $request->validated('search_query'),
            $request->validated('limit')
        ));
    }

    /**
     * Get All Messages
     *
     * Retrieves all messages between the authenticated user and the specified receiver.
     * Optionally filter by listing_id for listing-specific conversations.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|JsonResource
     */
    public function getAllMessages(string $receiverId)
    {
        $userId = auth()->id();
        $cursor = request('cursor');
        $listingId = request('listing_id');

        $chat = $this->chatService->getChat($userId, $receiverId, $listingId);

        if (! $chat) {
            return response()->json([
                'data' => [],
                'next_cursor' => null,
            ]);
        }

        $this->chatService->markMessagesAsRead($chat->id, $userId);
        
        // Get messages directly by chat_id for efficiency
        $messages = Message::with('listing.images')
            ->where('chat_id', $chat->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->cursorPaginate(10, ['*'], 'cursor', $cursor);
            
        return response()->json([
            'data' => MessageResource::collection($messages),
            'next_cursor' => $messages->nextCursor()?->encode(),
        ]);
    }

    /**
     * Send Message
     *
     * Sends a new message from the authenticated user to the specified receiver.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function sendMessage(SendMessageRequest $request)
    {
        $message = $this->chatService->sendMessage(
            auth()->id(),
            $request->validated('receiver_id'),
            $request->validated('content'),
            $request->validated('listing_id'),
        );

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => new MessageResource($message),
        ]);
    }
}
