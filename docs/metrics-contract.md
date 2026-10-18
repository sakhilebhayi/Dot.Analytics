# Dot.Analytics metrics contract

How a Dot platform sends its numbers to Dot.Analytics, and what Dot.Analytics does with them. This is the
only way data enters the intelligence layer: nothing is polled, scraped or seeded in production, and
nothing is shown that a platform did not send.

The machine-readable version is `config/metrics.php`; a test fails if this page and that file disagree.

## The data path

```
platform --signed daily snapshot--> POST /api/ingest/snapshots            (a platform, no user token)
                                    POST /api/v1/ingest/{platform}        (a team member's token, see below)
   -> analytics_snapshots          stored once, as received (validated)
   -> computed_metrics             value, previous value, delta, delta %, per metric per day
   -> anomaly alerts               only with >= 7 prior daily points (see "Anomalies")
   -> briefings, Business DNA and the AI engines read these same real snapshots
```

* Metrics are computed right after a new snapshot arrives and again every hour (`analytics:compute-metrics`). Both are idempotent.
* A metric a platform never reported has no row. The UI says "no data yet"; it never shows zero in its place.
* A latest value older than two daily periods is shown as **stale**, and is never used for anomaly detection.

## Publishing from a platform (server to server)

This is how a Dot platform publishes its own numbers. It needs **no user and no token**: the platform proves who it
is with a secret only it and Dot.Analytics know. The snapshot body is exactly the one described under "Body" further
down, and goes through the same validation, storage and metric computation as the user-token endpoint.

`POST /api/ingest/snapshots`

Headers:

| Header | Value |
|---|---|
| `Content-Type` | `application/json` |
| `X-InfoDot-Timestamp` | The current unix time in seconds. Must be within five minutes of Dot.Analytics' clock, either way. |
| `X-InfoDot-Signature` | `sha256=` + hex `HMAC-SHA256(secret, "{timestamp}.{raw body}")`, keyed with the platform's own secret |

This is the same scheme Dot.Notify's `POST /api/ingest/events` uses, so a platform that already publishes events
signs snapshots the same way. The signature covers the timestamp and the **exact bytes** of the request body, so sign
the string you actually send (do not re-encode it afterwards). A bare hex digest without the `sha256=` prefix is also
accepted.

### Secrets

Each platform has one secret, set in Dot.Analytics' environment as `ANALYTICS_INGEST_SECRET_<KEY>` and in the
publishing platform's own environment (never committed). Generate one with `php -r 'echo bin2hex(random_bytes(32));'`.

| Platform | Environment variable |
|---|---|
| `tasks` | `ANALYTICS_INGEST_SECRET_TASKS` |
| `hr` | `ANALYTICS_INGEST_SECRET_HR` |
| `auction` | `ANALYTICS_INGEST_SECRET_AUCTION` |
| `ehail` | `ANALYTICS_INGEST_SECRET_EHAIL` |
| `farms` | `ANALYTICS_INGEST_SECRET_FARMS` |
| `billing` | `ANALYTICS_INGEST_SECRET_BILLING` |
| `emall` | `ANALYTICS_INGEST_SECRET_EMALL` |
| `tutor` | `ANALYTICS_INGEST_SECRET_TUTOR` |
| `pulse` | `ANALYTICS_INGEST_SECRET_PULSE` |
| `notify` | `ANALYTICS_INGEST_SECRET_NOTIFY` |
| `design` | `ANALYTICS_INGEST_SECRET_DESIGN` |

* A **blank or absent** secret means that platform cannot call at all. Nothing is enabled until an operator sets one.
* The `platform` field in the body picks which secret is checked, so a platform can only ever publish **as itself**:
  a body naming another platform is checked against that platform's secret and refused. The `platform` must be one of
  the keys in the catalogue below, spelled exactly (lower case).
* Rotating a secret is changing the variable in both places; the old one stops working at once.
* Dot.Analytics never stores a signature, and never logs a secret, a signature or a request body.

### Worked example

Dot.Tasks publishes two numbers for a company. The secret below is an example, never a real one.

```
secret     example-tasks-secret-do-not-use
timestamp  1792400000
body       {"company_uuid":"3f2b8c1e-9a4d-4e7b-8c55-1d2e3f4a5b6c","platform":"tasks","taken_at":"2026-10-17T06:00:00Z","period":"day","metrics":{"open":12,"overdue":3}}
```

The string that is signed is the timestamp, a full stop, then the body, byte for byte:

```
1792400000.{"company_uuid":"3f2b8c1e-9a4d-4e7b-8c55-1d2e3f4a5b6c","platform":"tasks","taken_at":"2026-10-17T06:00:00Z","period":"day","metrics":{"open":12,"overdue":3}}
```

and the signature is:

```
sha256=82807766b9833aa9b2d5af9cc8e8bf6b4a45ef958e4e8363dc105724d93ad8ca
```

In PHP:

```php
$raw = json_encode($snapshot);                                  // sign what you send
$timestamp = time();
$signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, config('services.analytics.secret'));

Http::withHeaders([
    'X-InfoDot-Timestamp' => (string) $timestamp,
    'X-InfoDot-Signature' => $signature,
])->withBody($raw, 'application/json')->post($analyticsUrl.'/api/ingest/snapshots');
```

With curl and openssl (the timestamp must be fresh when you run it):

```
BODY='{"company_uuid":"3f2b8c1e-9a4d-4e7b-8c55-1d2e3f4a5b6c","platform":"tasks","taken_at":"2026-10-17T06:00:00Z","period":"day","metrics":{"open":12,"overdue":3}}'
TS=$(date +%s)
SIG=$(printf '%s.%s' "$TS" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -X POST https://analytics.infodot.app/api/ingest/snapshots \
  -H 'Content-Type: application/json' -H "X-InfoDot-Timestamp: $TS" -H "X-InfoDot-Signature: sha256=$SIG" \
  --data-binary "$BODY"
```

### What Dot.Analytics does, in order

1. Refuses bodies over 64 KB (`413`).
2. **Authenticates** the platform. Any failure is the same `401` with the same body (`{"error":"invalid_request"}`):
   unknown platform, blank secret, missing header, wrong secret, stale or future timestamp, a body changed after
   signing, a platform naming another platform. The caller is told nothing about which. The reason is written to the server log (no secrets).
3. Applies the platform's rate limit: 120 calls a minute per platform (`ANALYTICS_INGEST_RATE_LIMIT`), counting
   only authenticated calls, answered with `429` and `Retry-After`.
4. Checks the snapshot against the contract (`422`, with `errors` keyed by field, e.g. `metrics.open`).
5. Looks the company up by `company_uuid` in `teams.infodot_team_uuid`. An unknown company is a generic `404`
   (`{"error":"not_found"}`), and only ever after step 2, so it cannot be used to find out which companies exist.
6. Stores it for that team, idempotently, and refreshes the team's computed metrics.

The company is part of the signed body, so a platform's signature for company X cannot be replayed against
company Y, and a snapshot only ever lands in the one team its `company_uuid` maps to.

### The data source is created for you

The first accepted snapshot of a platform for a company **creates that team's data source for the platform**, already
connected and marked as a push source (`config.source_type = "push"`), so the dashboard shows the platform as
connected without anyone clicking "+ Connect". The owner does not need to issue a webhook secret for this path. A
source an owner already created is reused as it is (its webhook secret is untouched), and a pending one is
connected. If an owner disconnects a platform, the next accepted snapshot connects it again: to stop a platform, blank
its `ANALYTICS_INGEST_SECRET_<KEY>`.

### Server-to-server responses

| Status | Body | Meaning |
|---|---|---|
| `201` | `id`, `status: "stored"`, `replayed: false`, `platform`, `taken_at`, `period`, `accepted_keys`, `dropped_keys` | Stored. The same fields are repeated under `data` (with `data.snapshot_id`) in the envelope the user-token endpoint uses. |
| `200` | the same, `status: "replayed"`, `replayed: true` | Already stored: the same (company, platform, `taken_at`, `period`) was received before. `id` is the **original** snapshot's, and its numbers are kept even if the replay's differ, so a retry can never restate a day. |
| `401` | `{"error":"invalid_request"}` | Not authenticated, for any reason. |
| `404` | `{"error":"not_found"}` | No company with that `company_uuid`. |
| `413` | `{"error":"payload_too_large"}` | Body over 64 KB. |
| `422` | `{"error":"invalid_request","errors":{"metrics.open":["open must be a number."]}}` | The contract was broken; nothing was stored and no data source was created. |
| `429` | `{"error":"rate_limited"}` + `Retry-After` | The platform is over its rate limit. Retry after the delay. |

Retrying a request that timed out is safe: send it again with a fresh timestamp and signature.

## Sending a snapshot with a user token

The older way, still supported unchanged, for a team member's own tooling. `POST /api/v1/ingest/{platform}` where `{platform}` is the platform key (`tasks`, `billing`, ...).

Headers:

| Header | Value |
|---|---|
| `Authorization` | `Bearer <Sanctum token of a member of the company's team>` |
| `Content-Type` | `application/json` |
| `X-Analytics-Signature` | `sha256=` + hex HMAC-SHA256 of the **raw request body**, keyed with the platform's webhook secret |

This path is **not** the one a platform uses to publish; that is `POST /api/ingest/snapshots` above, which needs no user.

The webhook secret is issued once when an owner or admin connects the platform in Dot.Analytics
(`POST /api/v1/platforms/{platform}/connect`, or "+ Connect" on the dashboard), and can be rotated
(`POST /api/v1/platforms/{platform}/webhook-secret`). It is stored encrypted and cannot be read back. A platform
without a secret cannot ingest, and there is no unsigned fallback.

Body:

```json
{
  "company_uuid": "<InfoDot company uuid>",
  "platform": "<same as the URL>",
  "taken_at": "<ISO 8601 with a zone, e.g. 2026-10-17T06:00:00Z>",
  "period": "day",
  "metrics": {
    "<catalogue key>": "<number>",
    "currency": "<ISO 4217, only when a *_cents key is present>"
  }
}
```

(The values above are shapes, not data. Every number in a real snapshot comes from the platform.)

| Field | Rule |
|---|---|
| `company_uuid` | The company's InfoDot team uuid, which Dot.Analytics stores in `teams.infodot_team_uuid` (set by SSO sync or `infodot:link-team`). Required. |
| `platform` | Must equal the platform in the URL. |
| `taken_at` | ISO 8601 **with a zone**. Stored in UTC. Not more than five minutes in the future. |
| `period` | `day`. The only period accepted so far. |
| `metrics` | An object. Keys are the catalogue below. |

### Metric values

* **Unknown keys are dropped** (and listed in the response as `dropped_keys`); they are never stored.
* A value of the wrong kind is **rejected with 422** and nothing is stored: text where a number is required, a fraction where a whole number is required, a negative count, a rating outside 0 to 5.
* `null` means "not reported". It is neither stored nor treated as zero.
* A snapshot with no catalogue key at all is rejected.
* **Money is whole cents** in a `*_cents` key (R1 250.00 is `125000`), and the snapshot must carry `metrics.currency`, a three-letter ISO 4217 code that applies to every `*_cents` key in that snapshot. Deltas are never computed across a currency change.
* Numeric strings (`"12"`) are accepted where a number is required.

### Responses

| Status | Meaning |
|---|---|
| `201` | Stored. `data` has `snapshot_id`, `accepted_keys`, `dropped_keys`, `replayed: false`. |
| `200` | Already stored: the same (platform, `taken_at`, `period`) was received before. The **original** is returned untouched, even if the numbers differ, so a replay can never restate a day. `replayed: true`. |
| `401` | Missing or wrong signature, or the platform has no secret. |
| `404` | One generic answer for: unknown company, a company the token's user does not belong to, platform not connected. These cannot be told apart from outside. |
| `422` | The contract was broken. `errors` is keyed by field, e.g. `metrics.open`. |

### Idempotency

A snapshot is identified by (data source, platform, `taken_at`, `period`), whichever endpoint carried it: a snapshot
stored through `POST /api/ingest/snapshots` is a replay if the same one arrives on the user-token endpoint, and the
other way round. Retrying a request that timed out is safe. Two snapshots on the same UTC day are both kept; the later `taken_at` is the one the day's metric uses.

## Catalogue

Keys are per platform. Only these are accepted; everything else is dropped.

#### `tasks` (Dot.Tasks)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `open` | Open tasks | whole number >= 0 | lower |
| `overdue` | Overdue tasks | whole number >= 0 | lower |
| `completed_7d` | Tasks completed, 7 days | whole number >= 0 | higher |
| `created_7d` | Tasks created, 7 days | whole number >= 0 | no preference |

#### `auction` (Dot.Auction)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `active` | Active auctions | whole number >= 0 | no preference |
| `ended_7d` | Auctions ended, 7 days | whole number >= 0 | no preference |
| `gmv_7d_cents` | Gross sales, 7 days | whole cents >= 0 (needs `currency`) | higher |
| `bids_7d` | Bids placed, 7 days | whole number >= 0 | higher |

#### `ehail` (Dot.Ehail)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `rides_requested_7d` | Rides requested, 7 days | whole number >= 0 | higher |
| `rides_completed_7d` | Rides completed, 7 days | whole number >= 0 | higher |
| `rides_expired_7d` | Rides expired unanswered, 7 days | whole number >= 0 | lower |
| `drivers_online` | Drivers online | whole number >= 0 | higher |
| `avg_rating` | Average driver rating | number 0 to 5 | higher |

#### `hr` (Dot.HR)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `headcount` | Headcount | whole number >= 0 | no preference |
| `on_leave_today` | On leave today | whole number >= 0 | no preference |
| `pending_leave_requests` | Pending leave requests | whole number >= 0 | lower |

#### `farms` (Dot.Farms)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `active_cycles` | Active crop cycles | whole number >= 0 | no preference |
| `harvests_30d` | Harvests, 30 days | whole number >= 0 | higher |
| `harvested_kg_30d` | Harvested, 30 days (kg) | number >= 0 | higher |

#### `billing` (Dot.Billing)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `mrr_cents` | Monthly recurring revenue | whole cents >= 0 (needs `currency`) | higher |
| `open_invoices` | Open invoices | whole number >= 0 | no preference |
| `overdue_invoices` | Overdue invoices | whole number >= 0 | lower |
| `overdue_cents` | Overdue amount | whole cents >= 0 (needs `currency`) | lower |

#### `emall` (Dot.Emall)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `orders_7d` | Orders, 7 days | whole number >= 0 | higher |
| `revenue_7d_cents` | Revenue, 7 days | whole cents >= 0 (needs `currency`) | higher |
| `low_stock_products` | Low-stock products | whole number >= 0 | lower |

#### `tutor` (Dot.Tutor)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `sessions_7d` | Sessions booked, 7 days | whole number >= 0 | higher |
| `completed_7d` | Sessions completed, 7 days | whole number >= 0 | higher |
| `active_tutors` | Active tutors | whole number >= 0 | no preference |

#### `pulse` (Dot.Pulse)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `posts_7d` | Posts, 7 days | whole number >= 0 | no preference |
| `active_users_7d` | Active users, 7 days | whole number >= 0 | higher |
| `held_posts` | Posts held for review | whole number >= 0 | lower |

#### `notify` (Dot.Notify)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `sent_7d` | Messages sent, 7 days | whole number >= 0 | no preference |
| `failed_7d` | Messages failed, 7 days | whole number >= 0 | lower |
| `suppressed_7d` | Messages suppressed, 7 days | whole number >= 0 | no preference |

#### `design` (Dot.Design)

| Key | Meaning | Kind | Better when |
|---|---|---|---|
| `projects` | Design projects | whole number >= 0 | no preference |
| `assets` | Design assets | whole number >= 0 | no preference |

Money keys (`*_cents`) require `metrics.currency`.

Adding a metric means adding it to `config/metrics.php` (and this page): it then needs a platform that sends it.

## What is computed

One `computed_metrics` row per metric per UTC day, from the latest snapshot of that day:

| Field | Meaning |
|---|---|
| `value` | The reported number (cents for money). |
| `previous_value`, `previous_period_date` | The previous **reported** day for the same metric (days with no snapshot are skipped, not filled). |
| `delta`, `delta_pct` | Value minus previous value, and as a percent of the previous value. `delta_pct` is absent when the previous value was zero. Both are absent for the first point and across a currency change. |
| `period`, `period_date` | `daily` and the UTC date: the window. |
| `observed_at` | The snapshot's `taken_at`. Staleness is judged from this when read. |
| `currency` | For money metrics. |
| `is_demo` | True only for data seeded by the local Demo seeder. Shown as DEMO DATA. |

## Anomalies

After computing, a metric is flagged only when it has **at least 7 prior daily points** in the previous 30 days
and its latest point is not stale. The latest value is compared with the median of the prior points:

```
deviation = |latest - median| / max(MAD, 1% of |median|, minimum scale for the metric kind)
flag when deviation > 3.5            (config metrics.anomaly.mad_threshold, env ANALYTICS_ANOMALY_MAD)
```

`MAD` is the median absolute deviation of the prior points. The floors stop a flat history from turning any wobble
into an alert. At most one alert is raised per metric per day; it carries its provenance (metric, window, value,
median, MAD, threshold, the prior points used). No AI is involved.

## Cron and queue requirements

* The Laravel scheduler must run every minute (`* * * * * php artisan schedule:run`). It runs `analytics:compute-metrics` hourly, which computes metrics and then checks them for anomalies, and `analytics:run-scheduled-reports` every minute, which runs saved reports whose cron expression is due (read in the team timezone; each run is kept in the report's run history, nothing is emailed).
* A queue worker must run for the `ComputeMetricsJob` that refreshes a team's tiles right after a snapshot arrives. Without a worker the hourly run still computes everything.

## Who reads the numbers

The dashboard (KPI tiles, trends, saved dashboards) reads computed metrics. Executive briefings and Business DNA are written by an AI model from these same computed metrics and the real snapshots, only when a provider is configured; with no data, or no provider, they say so and store nothing. A metric that was never reported reaches the model as "no data yet", a stale one as STALE.

## Not part of this contract

Pull connectors, data pipelines, AI-to-SQL and the currency conversion service exist in the codebase but nothing
creates a connector, so they are not wired in and play no part in the data path.
