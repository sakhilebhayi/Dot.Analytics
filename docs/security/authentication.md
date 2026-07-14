# Authentication Hardening — Implementation Guide

**Scorecard domains:** IAM (62/100), Authentication (68/100)
**Target:** IAM 80/100, Authentication 82/100

---

## Fix 1 — Password Policy (Quick Win, 30 minutes)

Edit `app/Actions/Fortify/PasswordValidationRules.php`:

```php
use Illuminate\Validation\Rules\Password;

public static function rules(): array
{
    return [
        'password' => [
            'required',
            'string',
            Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(), // Checks HaveIBeenPwned API
            'confirmed',
        ],
    ];
}
```

> `uncompromised()` uses the HaveIBeenPwned k-anonymity API — the full password
> is never sent; only a 5-character prefix of the SHA-1 hash.

---

## Fix 2 — IP-Based Lockout (Quick Win, 1 hour)

Edit `app/Providers/FortifyServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Existing Fortify config...

    // Per-IP exponential backoff on login attempts
    RateLimiter::for('login', function (Request $request) {
        $key = 'login:' . $request->ip();
        $attempts = Cache::get($key . ':attempts', 0);

        return Limit::perMinutes(
            min(pow(2, $attempts), 60), // 1, 2, 4, 8, 16, 32, 60 minutes
            1
        )->by($request->ip())
         ->response(fn () => response()->json([
             'message' => 'Too many login attempts. Please try again later.',
         ], 429));
    });
}
```

---

## Fix 3 — Login Events in Audit Log (1-2 hours)

Create `app/Listeners/RecordAuthEvent.php`:

```php
<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;

class RecordAuthEvent
{
    public function handleLogin(Login $event): void
    {
        $this->record($event->user->currentTeam?->id, $event->user->id, 'auth.login.success');
    }

    public function handleFailed(Failed $event): void
    {
        AuditLog::create([
            'team_id'    => null,
            'user_id'    => null,
            'actor_type' => 'unknown',
            'event'      => 'auth.login.failed',
            'new_values' => ['email' => $event->credentials['email'] ?? null],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'occurred_at' => now(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record($event->user->currentTeam?->id, $event->user->id, 'auth.logout');
    }

    private function record(?int $teamId, int $userId, string $event): void
    {
        AuditLog::create([
            'team_id'    => $teamId,
            'user_id'    => $userId,
            'actor_type' => 'user',
            'actor_id'   => (string) $userId,
            'event'      => $event,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
```

Register in `app/Providers/EventServiceProvider.php`:

```php
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use App\Listeners\RecordAuthEvent;

protected $listen = [
    Login::class   => [RecordAuthEvent::class . '@handleLogin'],
    Failed::class  => [RecordAuthEvent::class . '@handleFailed'],
    Logout::class  => [RecordAuthEvent::class . '@handleLogout'],
    // existing...
];
```

---

## Fix 4 — MFA Enforcement for Privileged Roles (2-3 hours)

Create `app/Http/Middleware/RequireMfaForAdmins.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMfaForAdmins
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $isAdmin = $user->currentTeam?->user_id === $user->id
            || $user->hasTeamRole($user->currentTeam, 'admin');

        if ($isAdmin && ! $user->two_factor_confirmed_at) {
            return response()->json([
                'message' => 'Multi-factor authentication is required for administrator accounts.',
                'mfa_required' => true,
                'setup_url' => url('/user/two-factor-authentication'),
            ], 403);
        }

        return $next($request);
    }
}
```

Apply to sensitive API routes:

```php
Route::middleware(['auth:sanctum', RequireMfaForAdmins::class])->group(function () {
    Route::post('platforms/{platform}/connect', ...);
    Route::post('intelligence/run', ...);
    Route::patch('feature-flags/{key}/enable', ...);
});
```

---

## Fix 5 — OIDC (Enterprise SSO via Okta / Azure AD)

```bash
composer require laravel/socialite
composer require socialiteproviders/microsoft-azure  # or okta, google
```

Create `app/Http/Controllers/Auth/OidcController.php`:

```php
public function redirect(string $provider): RedirectResponse
{
    // Validate provider is in allowlist
    abort_unless(in_array($provider, ['azure', 'google', 'okta']), 404);
    return Socialite::driver($provider)->redirect();
}

public function callback(string $provider): RedirectResponse
{
    $socialUser = Socialite::driver($provider)->user();

    $user = User::firstOrCreate(
        ['email' => $socialUser->getEmail()],
        ['name'  => $socialUser->getName(), 'password' => Str::random(32)],
    );

    Auth::login($user);
    return redirect('/dashboard');
}
```

Add to `config/services.php`:
```php
'azure' => [
    'client_id'     => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'redirect'      => env('AZURE_REDIRECT_URI'),
    'tenant'        => env('AZURE_TENANT_ID'),
],
```

---

## Checklist

- [ ] Password policy strengthened with complexity + breach check
- [ ] IP-based exponential backoff on login
- [ ] Auth events (login/fail/logout/MFA) logged to `audit_logs`
- [ ] MFA enforcement middleware for admin routes
- [ ] OIDC provider integrated (Azure AD / Okta / Google)
- [ ] Tests written for all new flows
- [ ] Scorecard updated
