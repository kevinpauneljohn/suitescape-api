<?php

use App\Models\Listing;
use App\Models\Video;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('private-chat.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('private-active-status.{id}', function ($user) {
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

    return $user->id === $id;
});
