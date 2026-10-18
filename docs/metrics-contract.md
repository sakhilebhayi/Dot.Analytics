# Dot.Analytics metrics contract

How a Dot platform sends its numbers to Dot.Analytics, and what Dot.Analytics does with them. This is the
only way data enters the intelligence layer: nothing is polled, scraped or seeded in production, and
nothing is shown that a platform did not send.

The machine-readable version is `config/metrics.php`; a test fails if this page and that file disagree.

## The data path

```
platform --signed daily snapshot--> POST /api/v1/ingest/{platform}
   -> analytics_snapshots          stored once, as received (validated)
   -> computed_metrics             value, previous value, delta, delta %, per metric per day
   -> anomaly alerts               only with >= 7 prior daily points (see "Anomalies")
   -> briefings, Business DNA and the AI engines read these same real snapshots
```

* Metrics are computed right after a new snapshot arrives and again every hour (`analytics:compute-metrics`). Both are idempotent.
* A metric a platform never reported has no row. The UI says "no data yet"; it never shows zero in its place.
* A latest value older than two daily periods is shown as **stale**, and is never used for anomaly detection.

## Sending a snapshot

`POST /api/v1/ingest/{platform}` where `{platform}` is the platform key (`tasks`, `billing`, ...).

Headers:

| Header | Value |
|---|---|
| `Authorization` | `Bearer <Sanctum token of a member of the company's team>` |
| `Content-Type` | `application/json` |
| `X-Analytics-Signature` | `sha256=` + hex HMAC-SHA256 of the **raw request body**, keyed with the platform's webhook secret |

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

A snapshot is identified by (data source, platform, `taken_at`, `period`). Retrying a request that timed out is
safe. Two snapshots on the same UTC day are both kept; the later `taken_at` is the one the day's metric uses.

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
