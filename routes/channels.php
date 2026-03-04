<?php

use App\Models\Listing;
use App\Models\Video;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('private-notification.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('chat.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('active-status.{id}', function ($user, $id) {
    // Any authenticated user can listen to anyone's active status
    return $user !== null;
});

Broadcast::channel('private-payment.{id}', function ($user) {
    return (bool) $user;
});

Broadcast::channel('private-video-transcoding.{id}', function ($user, $id) {
    $video = Video::find($id);
    $listing = Listing::find($id);

    if ($video) {
        return $video->isOwnedBy($user);
    }

    if ($listing) {
        return $listing->user_id === $user->id;
    }

    return (string) $user->id === (string) $id;
});