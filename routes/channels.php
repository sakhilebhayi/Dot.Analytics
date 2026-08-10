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
| {teamId} is deliberately not int-typed on the closure parameters: a
| malformed value (e.g. subscribing to "private-team.not-a-number.alerts")
| would otherwise throw an uncaught TypeError while PHP tries to coerce it
| to an `int` parameter -- an unauthorized 500 instead of a clean,
| fail-closed 403. See docs/DOT_REALTIME_STANDARD.md (Dot.Mines) §6.
|
*/

// Team intelligence channel — receives real-time engine completions and critical alerts
Broadcast::channel('team.{teamId}.intelligence', function ($user, $teamId) {
    return is_string($teamId) && ctype_digit($teamId) && $user->currentTeam?->id === (int) $teamId;
});

// Team alerts channel — receives real-time critical insight notifications
Broadcast::channel('team.{teamId}.alerts', function ($user, $teamId) {
    return is_string($teamId) && ctype_digit($teamId) && $user->currentTeam?->id === (int) $teamId;
});
