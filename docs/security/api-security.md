# API Security — Webhook Replay Protection & Hardening

**Scorecard domain:** API Security (currently 82/100)
**Target:** 95/100

---

## Fix 1 — Webhook Replay Protection (Critical)

A captured valid HMAC payload can currently be replayed indefinitely.

Update `app/Http/Controllers/Api/V1/IngestController.php`:

```php
public function receive(Request $request, string $platform): JsonResponse
{
    // ... existing platform lookup ...

    $secret = $source->config['webhook_secret'] ?? null;
    if ($secret) {
        if (! $this->validateSignature($request, $secret)) {
            return $this->error('Invalid signature.', 401);
        }

        // Replay protection: reject stale or replayed requests
        if (! $this->validateTimestamp($request)) {
            return $this->error('Request timestamp is too old or missing. Replay rejected.', 401);
        }

        if (! $this->validateNonce($request, $source->id)) {
            return $this->error('Duplicate request detected. Replay rejected.', 409);
        }
    }

    // ... existing job dispatch ...
}

/**
 * Reject requests older than 5 minutes.
 */
private function validateTimestamp(Request $request): bool
{
    $timestamp = (int) $request->header('X-Analytics-Timestamp', 0);

    if ($timestamp === 0) {
        return false;
    }

    return abs(time() - $timestamp) <= 300; // 5-minute window
}

/**
 * Reject replayed signatures using Redis nonce cache.
 */
private function validateNonce(Request $request, int $sourceId): bool
{
    $signature = $request->header('X-Analytics-Signature', '');
    $nonceKey  = "webhook:nonce:{$sourceId}:" . hash('sha256', $signature);

    if (Cache::has($nonceKey)) {
        return false; // Already seen this exact request
    }

    Cache::put($nonceKey, true, 300); // Cache for 5-minute window
    return true;
}
```

**Sender instructions** — add to `docs/integrations/webhook-setup.md`:

```
Headers required on every webhook call:
  X-Analytics-Signature: sha256=<HMAC-SHA256(secret, raw_body)>
  X-Analytics-Timestamp: <unix_timestamp>
  X-Analytics-Platform: dot.fleet
```

---

## Fix 2 — Response Field Filtering

Ensure API responses never include fields intended for internal use.

Add `$hidden` to models exposed via API:

```php
// In DataSource:
protected $hidden = ['config', 'capabilities'];  // Config contains credentials

// In CrossPlatformInsight:
protected $hidden = ['supporting_metrics'];  // Internal only
```

For collection responses, use API Resources:

```bash
php artisan make:resource DataSourceResource
php artisan make:resource CrossPlatformInsightResource
```

```php
// app/Http/Resources/DataSourceResource.php
public function toArray(Request $request): array
{
    return [
        'id'           => $this->id,
        'platform'     => $this->platform,
        'display_name' => $this->display_name,
        'status'       => $this->status,
        'connected_at' => $this->connected_at?->toIso8601String(),
        'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        // Note: 'config' and 'capabilities' deliberately excluded
    ];
}
```

---

## Fix 3 — API Versioning Header

Add `Accept-Version` validation to allow graceful deprecation:

```php
// In BaseApiController:
protected function requireApiVersion(Request $request, string $minimum = 'v1'): void
{
    $version = $request->header('Accept-Version', 'v1');
    $supported = ['v1'];

    if (! in_array($version, $supported)) {
        abort(400, "API version '{$version}' is not supported. Use: " . implode(', ', $supported));
    }
}
```

---

## Fix 4 — Rate Limit Response Headers

Expose rate limit status in responses so clients can back off gracefully:

```php
// In AppServiceProvider::boot():
RateLimiter::for('analytics-api', function (Request $request) {
    return Limit::perMinute(120)
        ->by($request->user()?->id ?? $request->ip())
        ->response(function (Request $req, array $headers) {
            return response()->json([
                'message'     => 'Too many requests.',
                'retry_after' => $headers['Retry-After'] ?? 60,
            ], 429, $headers);
        });
});
```

---

## Security Headers for API Responses

All API responses automatically get these via `SecurityHeaders` middleware:

| Header | Value | Purpose |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `X-Frame-Options` | `SAMEORIGIN` | Clickjacking |
| `X-XSS-Protection` | `1; mode=block` | XSS legacy browsers |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Referrer leakage |
| `Content-Security-Policy` | see SecurityHeaders.php | XSS + injection |

---

## Checklist

- [ ] `X-Analytics-Timestamp` validation added to `IngestController`
- [ ] Redis nonce cache added for replay protection
- [ ] API Resources created for `DataSource`, `CrossPlatformInsight`, `Recommendation`
- [ ] Sensitive fields removed from public API responses
- [ ] Rate limit headers exposed in 429 responses
- [ ] Webhook sender documentation created
- [ ] Tests added for replay protection
- [ ] Scorecard updated
