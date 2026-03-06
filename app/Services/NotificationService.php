<?php

namespace App\Services;

use App\Events\NewNotification;
use App\Models\Notification;

class NotificationService
{
    public function getUserNotifications()
    {
        $user = auth()->user();

        return $user->userNotifications()->orderByDesc('created_at')->get();
    }

    public function createNotification(array $notificationData)
    {
        $notification = Notification::create($notificationData);

        \Log::info('📬 Broadcasting NewNotification', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'title' => $notification->title,
        ]);

        broadcast(new NewNotification($notification));

        return $notification;
    }

    public function markAsRead(Notification $notification)
    {
        return $notification->update(['is_read' => true]);
    }
}
