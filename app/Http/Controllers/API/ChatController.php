<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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

    public function getAllChats()
    {
        return ChatResource::collection($this->chatService->getAllChats());
    }

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

    public function sendMessage(SendMessageRequest $request)
    {
        $message = $this->chatService->sendMessage(
            auth()->id(),
            $request->validated()['receiver_id'],
            $request->validated()['content'],
        );

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => new MessageResource($message),
        ]);
    }
}
