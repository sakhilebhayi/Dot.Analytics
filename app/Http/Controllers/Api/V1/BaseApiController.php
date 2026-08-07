<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

abstract class BaseApiController extends Controller
{
    /**
     * Resolve the authenticated user's current team, aborting with 403 if none.
     *
     * No route in this API group runs a team-context middleware that guarantees
     * current_team_id is set — a user who belongs to no team (e.g. removed from
     * their last team) reaches these actions with currentTeam genuinely null.
     * Falling through to an unscoped query in that case would leak every team's
     * data, so we abort instead of guessing. Centralised here since every
     * V1 controller was repeating the same `Auth::user()->currentTeam` lookup.
     */
    protected function currentTeam(): Team
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            abort(403, 'No active team selected.');
        }

        return $team;
    }

    protected function success(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ]), $status);
    }

    protected function paginated(mixed $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
