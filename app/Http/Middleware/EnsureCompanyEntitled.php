<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use App\Services\InfoDot\EntitlementCheck;
use App\Support\EntitlementRefusal;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in area honours the company's entitlement (R-50).
 *
 * InfoDot decides whether a company holds Dot.Analytics. A person who is already signed in must stop working when that
 * ends, so each request in the signed-in area asks (through a one-minute cache) whether the company whose figures are
 * being opened still holds the platform and whether InfoDot has the platform switched on (R-61). A platform switched
 * off answers with a plain 503 page (JSON for JSON and Livewire callers, with Retry-After 60) and wins over a refusal.
 * A clear "no" to holding it answers with the plain refusal page and HTTP 403: nothing is read from or written to the
 * workspace. When InfoDot cannot be reached the last clear answer under six hours old stands; with none ever stored,
 * the request goes through exactly as before.
 *
 * It sits on the signed-in route group (routes/web.php) and on Jetstream's own pages, and is
 * a Livewire persistent middleware, so component updates from those pages are checked too. It is not on sign-in,
 * sign-out, the handoff receiver, health checks, the machine-to-machine API, ingest or public pages.
 */
class EnsureCompanyEntitled
{
    /** Routes inside the checked groups that must keep working for a person whose company is refused. */
    private const EXEMPT = [
        'current-team.update', // switching to another company
        'profile.show',        // the person's own account (its Livewire forms include account deletion)
        'me.export', 'me.erase', // data-subject rights: a person's own export and erasure are never blocked by a commercial state
        'terms.show', 'policy.show', 'team-invitations.accept',
    ];

    /** Session key: redeem's `entitled` for the person's personal workspace, remembered at sign-in (absent: not set). */
    public const PERSONAL = 'infodot.personal_entitled';

    public function __construct(private readonly EntitlementCheck $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->entitlements->enabled() || $request->routeIs(...self::EXEMPT)) {
            return $next($request);
        }

        $user = $request->user() ?? Auth::guard('web')->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $team = $this->company($request, $user);

        // A personal workspace, or a team InfoDot does not know: the read endpoint is never asked. What InfoDot said
        // about the person's personal workspace at sign-in decides: exactly false is a refusal (individuals hold
        // the free core only); true, or nothing remembered, carries on.
        if (! $team) {
            return $next($request);
        }

        if ($team->personal_team || blank($team->infodot_team_uuid)) {
            if (! $request->hasSession() || $request->session()->get(self::PERSONAL) !== false) {
                return $next($request);
            }

            return $this->refuse($request, fn () => EntitlementRefusal::response(
                'company_required',
                null,
                $user->allTeams()->reject(fn ($other) => $other->id === $team->id || $other->personal_team || blank($other->infodot_team_uuid)),
            ));
        }

        $answer = $this->entitlements->forCompany((string) $team->infodot_team_uuid);

        // R-61: a platform InfoDot has switched off (maintenance, unavailable) is off for everyone, whatever they hold,
        // and that is a different question from what the company holds, so it is answered first. Nothing is changed.
        if ($answer !== null && $answer['available'] === false) {
            return $this->unavailable($request, $answer['message']);
        }

        if ($answer === null || $answer['entitled'] === true) {
            return $next($request);
        }

        return $this->refuse($request, fn () => EntitlementRefusal::response(
            $answer['status'],
            $team->name,
            $user->allTeams()->reject(fn ($other) => $other->id === $team->id),
        ));
    }

    /**
     * The 503 page, or JSON for JSON and Livewire callers. Livewire discards a non-redirect response from a persistent
     * middleware, which would let the update run, so on a Livewire update the answer is thrown instead.
     */
    private function unavailable(Request $request, ?string $message): Response
    {
        $response = EntitlementRefusal::unavailable($request, $message);

        if ($request->headers->has('X-Livewire') || request()->headers->has('X-Livewire')) {
            throw new HttpResponseException($response);
        }

        return $response;
    }

    /**
     * Livewire throws away any non-redirect response a persistent middleware returns, so a refusal page would let a
     * component update run. On a Livewire update the refusal must abort: nothing runs and no component comes back.
     */
    private function refuse(Request $request, Closure $page): Response
    {
        if ($request->headers->has('X-Livewire') || request()->headers->has('X-Livewire')) {
            abort(403);
        }

        return $page();
    }

    /**
     * Whether InfoDot has clearly said this company does not hold the platform. Used by the broadcasting channels,
     * which are authorised outside the route groups. Unknown is never a refusal.
     */
    public static function refuses(?Team $team): bool
    {
        $check = app(EntitlementCheck::class);

        if (! $team || ! $check->enabled()) {
            return false;
        }

        if ($team->personal_team || blank($team->infodot_team_uuid)) {
            return request()->hasSession() && request()->session()->get(self::PERSONAL) === false;
        }

        $answer = $check->forCompany((string) $team->infodot_team_uuid);

        return $answer !== null && ($answer['available'] === false || $answer['entitled'] !== true);
    }

    /** The company whose workspace this request opens: the one in the address when the person belongs to it, else their current one. */
    private function company(Request $request, User $user): ?Team
    {
        $named = $request->route('team');

        if (is_scalar($named)) {
            $named = $user->allTeams()->firstWhere('id', (int) $named);
        }

        if ($named instanceof Team && $user->belongsToTeam($named)) {
            return $named;
        }

        $current = $user->currentTeam;

        return $current instanceof Team ? $current : null;
    }
}
