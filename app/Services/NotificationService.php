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

        broadcast(new NewNotification($notification));

        return $notification;
    }

    public function markAsRead(Notification $notification)
    {
        return $notification->update(['is_read' => true]);
    }
}
