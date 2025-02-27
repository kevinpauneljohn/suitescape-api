<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->middleware('auth:sanctum');

        $this->notificationService = $notificationService;
    }

    public function getUserNotifications()
    {
        $notifications = $this->notificationService->getUserNotifications();

        return NotificationResource::collection($notifications);
    }

    public function markAsRead(string $id)
    {
        $notification = Notification::findOrFail($id);

        $this->notificationService->markAsRead($notification);

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => new NotificationResource($notification),
        ]);
    }
}
