<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\MessageResource;
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
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllChats()
    {
        return ChatResource::collection($this->chatService->getAllChats());
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
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|JsonResource
     */
    public function getAllMessages(string $receiverId)
    {
        $userId = auth()->id();
        $chat = $this->chatService->getChat($userId, $receiverId);

        if (! $chat) {
            return new JsonResource([]);
        }

        $this->chatService->markMessagesAsRead($chat->id, $userId);

        return MessageResource::collection($this->chatService->getMessages($userId, $receiverId));
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
