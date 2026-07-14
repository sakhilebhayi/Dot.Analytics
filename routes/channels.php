<?php

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

// Team intelligence channel — receives real-time engine completions and critical alerts
Broadcast::channel('team.{teamId}.intelligence', function ($user, int $teamId) {
    return $user->currentTeam?->id === $teamId;
});

// Team alerts channel — receives real-time critical insight notifications
Broadcast::channel('team.{teamId}.alerts', function ($user, int $teamId) {
    return $user->currentTeam?->id === $teamId;
});
