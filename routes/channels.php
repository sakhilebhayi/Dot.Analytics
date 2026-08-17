<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Each channel is authorised below. Private channels require the user
| to be authenticated and belong to the team whose ID is in the channel name.
|
*/

// Default per-user notification channel -- Notifiable::receivesBroadcastNotificationsOn()
// broadcasts every ShouldQueue notification on 'App.Models.User.{id}' by default
// (layouts/app.blade.php subscribes to exactly this channel). Without an explicit
// authorization callback here, POST /broadcasting/auth 403s for every subscription
// attempt, so the browser's Echo client can never actually receive anything -- the
// notification still broadcasts fine server-side, it's just unreachable client-side.
// Found by watching the notification bell fail to update live and tracing the
// resulting 403 on /broadcasting/auth back to this missing registration.
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Team intelligence channel — receives real-time engine completions and critical alerts
Broadcast::channel('team.{teamId}.intelligence', function ($user, int $teamId) {
    return $user->currentTeam?->id === $teamId;
});

// Team alerts channel — receives real-time critical insight notifications
Broadcast::channel('team.{teamId}.alerts', function ($user, int $teamId) {
    return $user->currentTeam?->id === $teamId;
});
