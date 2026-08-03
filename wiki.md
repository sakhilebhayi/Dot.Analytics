---
title: Dot.Analytics — Platform Wiki
version: 1.2.0
status: active
owners: [Analytics Platform Lead]
platform-id: dot-analytics
last-review: 2026-08-01
---

# Dot.Analytics

Purpose: this is Dot.Analytics's own knowledge home — owned and maintained by the Dot.Analytics team. It describes what this platform actually is, what it actually stores and computes, and how it connects to the wider Dot Ecosystem. Dot.Brain never edits this file; it only reads what we choose to publish.

> **Related:** [Dot.Brain's ingested view of this platform](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-analytics.md)

---

## 1. What Dot.Analytics Is

Dot.Analytics is a Laravel 12 / PHP 8.4 application — a multi-tenant intelligence platform that ingests snapshots pushed from other Dot platforms, runs a set of "intelligence engines" over that data, and surfaces cross-platform insights, alerts, recommendations, and executive briefings through both a Livewire dashboard and a versioned JSON API.

**Status:** this is a built, running application, not a blueprint. The repo has migrations, models, services, jobs, Livewire panels, API controllers, and console commands wired end-to-end. It is early-stage in maturity (6 commits, no production deployment evidence in-repo) but the core domain — connect a platform, ingest a snapshot, run engines, produce insights/alerts/recommendations, publish reports — is fully implemented, not aspirational.

Positioning per the README: it presents itself as "the central nervous system" of the ecosystem — not a BI tool that visualizes data users prepare, but a system that continuously consumes data pushed by other platforms and derives intelligence no single platform could produce alone (its worked examples: a mining productivity drop traced across HR/Fleet/Finance/Assets; an at-risk customer traced across sentiment/sales/support/billing/contracts). Note the README's platform list (Dot.Fleet, Dot.CRM, Dot.Documents, Dot.Hear, Dot.Vault, Dot.Security, etc.) is an earlier/different roster than the current Dot Ecosystem list in Dot.Brain's `CLAUDE.md` — see §7 for how that reconciles.

## 2. Architecture — How It's Actually Built

| Layer | Technology (verified in `composer.json` / README) |
|---|---|
| Backend framework | Laravel 12, PHP 8.4 |
| Frontend | Livewire 3 + Alpine.js + Tailwind CSS |
| Auth | Jetstream 5 + Sanctum, plus a custom `EcosystemAuthController` for ecosystem SSO (`/auth/ecosystem`, hands off from InfoDot) |
| Database | PostgreSQL 16 |
| AI | Anthropic Claude Sonnet via `App\Services\AiModelRouter`, with a deterministic rule-based fallback when no API key is set (`AiSqlService`, `AnomalyDetectionService`) |
| Queue / scheduling | Laravel queues + `Schedule::command(...)` jobs in `routes/console.php` |
| Multi-tenancy | Team-scoped (Jetstream `Team` model); every domain table carries `team_id` |

**Key modules present in `app/`:**

- **Ingestion** — `IngestController` (`POST /api/v1/ingest/{platform}`) accepts pushed snapshots; `IngestPlatformSnapshotJob` and `AnalyticsSnapshot` model persist them; `DataSource` + `ConnectorRegistry`/`DatabaseConnector`/`FileConnector`/`RestApiConnector` model *how* a platform is connected (webhook push vs. pull).
- **Intelligence engines** — `IntelligenceEngineService` defines a `PLATFORMS` catalog and an `ENGINES` catalog (engine keys observed include `data`, `business`, `operational`, `financial`, `people`, `customer`, `document`, `community`, `ai`, `predictive`, `decision`, `risk`, `compliance`, `asset`, `mining`, `agriculture`, `construction` — matching the README's "17 Intelligence Engines" table). `RunIntelligenceEnginesAction` / `RunIntelligenceEngineJob` execute them per team; `RunIntelligenceEnginesCommand` schedules all teams every 6 hours.
- **Cross-platform insight discovery** — `CrossPlatformIntelligenceService` + `CrossPlatformInsight` model; `AnomalyDetectionService` for signal detection; `CriticalInsightDiscovered` event fans out to `NotifyOnCriticalInsight`.
- **Knowledge graph** — `KnowledgeGraphService` with `IntelligenceNode` / `IntelligenceEdge` models, exposed via `GET /api/v1/intelligence/graph` and `POST /api/v1/intelligence/graph/traverse`.
- **Business DNA** — `BusinessDnaService` + `BusinessDnaProfile` model, recomputed nightly by `RecomputeDnaCommand` (02:00 daily), emits `BusinessDnaRecomputed` with confidence-score deltas.
- **Metrics** — `MetricDefinition` → `ComputedMetric`, seeded per the README with 55+ metric definitions, each keyed to a `source_platform` and `engine`.
- **Alerts & recommendations** — `AnalyticsAlert`, `Recommendation` models surfaced on the dashboard and via `GET /api/v1/intelligence/insights`.
- **Reporting** — `ReportGenerationService`, `AnalyticsReport`/`ReportRun`/`SavedReportController` support JSON/CSV/HTML report export (`/api/v1/reports/{type}[.csv|.html]`) and CSV download from the web dashboard.
- **Executive briefings** — `GenerateExecutiveBriefingAction`/`Job`, scheduled daily (06:00), weekly (Mon 06:30), and monthly (1st, 07:00) via `GenerateBriefingsCommand`.
- **AI-to-SQL** — `AiSqlService` + `SqlController` (`POST /api/v1/sql/query`, `/generate`) — natural-language-to-SQL, rate-limited separately (`throttle:analytics-ai`).
- **Feature flags** — `FeatureFlagService` + `FeatureFlag` model + `FeatureFlagController`, with per-flag enable/disable/rollout endpoints.
- **Audit** — `AuditLog` model; `AiModelUsage` tracks AI cost/usage per call.

**API surface** (`routes/api.php`, Sanctum-authenticated under `/api/v1`): `intelligence/{engines,insights,graph,graph/traverse,run}`, `platforms/{catalog,connected,show,connect,disconnect}`, `metrics/{index,definitions,ai-usage}`, `reports/{type}[.csv|.html]`, `sql/{query,generate}`, `ingest/{platform}[/ping]`, `saved-reports/*`, `feature-flags/*`, plus `/health`, `/health/detailed`, `/ping`. (Note: `routes/api.php` currently defines the `v1` group twice — a near-duplicate block later in the file with a slightly different route set — worth a cleanup pass, flagged under Roadmap.)

## 3. Domain Entities It Owns

From `database/migrations/2026_06_29_000001_create_analytics_tables.php`, `2026_07_14_000001_enhance_intelligence_schema.php`, and `2026_07_14_000002_enterprise_intelligence_schema.php`, plus `app/Models/`:

| Entity | Model | Notes |
|---|---|---|
| Data source | `DataSource` | a connected external platform per team; status (connected/disconnected), `platform` key |
| Analytics snapshot | `AnalyticsSnapshot` | raw ingested payload from a platform push |
| Data connector / pipeline | `DataConnector`, `DataPipeline`, `PipelineRun` | connection method + execution history |
| Intelligence engine run | `IntelligenceEngineRun` | audit record of an engine execution |
| Cross-platform insight | `CrossPlatformInsight` | multi-platform discovery, feeds `CriticalInsightDiscovered` |
| Intelligence node / edge | `IntelligenceNode`, `IntelligenceEdge` | the knowledge graph |
| Business DNA profile | `BusinessDnaProfile` | evolving org fingerprint with confidence score |
| Metric definition / computed metric | `MetricDefinition`, `ComputedMetric` | metric catalog + per-team computed values |
| Analytics alert | `AnalyticsAlert` | severity-ranked cross-platform signal |
| Recommendation | `Recommendation` | AI-generated action, pending/accepted/etc. |
| Analytics dashboard / widget | `AnalyticsDashboard`, `DashboardWidget` | dashboard builder |
| Analytics report / report run | `AnalyticsReport`, `ReportRun` | saved report definitions + execution history |
| Executive briefing | `ExecutiveBriefing` | scheduled daily/weekly/monthly summary |
| Feature flag | `FeatureFlag` | runtime feature toggles with rollout % |
| Audit log | `AuditLog` | general audit trail |
| AI model usage | `AiModelUsage` | per-call AI cost/usage tracking |
| Team / membership / invitation | `Team`, `Membership`, `TeamInvitation` | Jetstream tenancy |

This is broader than the "5 entities" framing in Dot.Brain's ingested view (§2 of `platforms/dot-analytics.md`) — the Brain doc's entity table reflects the ecosystem-facing reporting contract (KPI definitions, dashboard products, composite views), which is one layer of what this codebase implements; the codebase itself is closer to a full intelligence platform with its own ingestion, graph, and DNA-profiling subsystems underneath that contract.

## 4. Events It Emits

Verified in `app/Events/Analytics/`:

| Event | Trigger | Notes |
|---|---|---|
| `PlatformConnected` | A `DataSource` is connected for a team | carries `DataSource`, `teamId` |
| `PlatformDisconnected` | A `DataSource` is disconnected | carries `teamId`, `platform`, `displayName` |
| `IntelligenceEngineCompleted` | An engine run finishes | `implements ShouldBroadcast`, broadcast as `engine.completed` on `team.{teamId}.intelligence` (see `routes/channels.php`) |
| `CriticalInsightDiscovered` | A high-severity `CrossPlatformInsight` is found | consumed by `NotifyOnCriticalInsight` listener; broadcasts on `team.{teamId}.alerts` |
| `BusinessDnaRecomputed` | Nightly DNA recompute completes | carries confidence score + previous confidence score (delta) |

These are internal Laravel events (broadcast over team-scoped channels for the Livewire UI), not yet the same thing as the ecosystem-level Knowledge Pack events described in Dot.Brain's ingested view (`analytics.kpi.published/restated`, `analytics.view.created/retired`, `analytics.catalog.synced`). No code in this repo currently emits DKP-formatted events or writes to a `platform.dkp.json` manifest — that integration layer is not yet built (see §6, §7).

## 5. Multi-Tenancy

Every domain table is team-scoped (`team_id`), using Jetstream's `Team` model as the tenant boundary. Policies exist for `Team`, `DataSource`, and `CrossPlatformInsight` (`app/Policies/`), consistent with per-team data isolation. There is no evidence in-repo yet of the "tenant key = subscribing organization" cross-tenant aggregation-floor model described in Dot.Brain's ingested view §7 — that is an ecosystem-integration concept layered on top of, not yet implemented within, this codebase's tenancy.

## 6. Connecting to Dot.Brain

Dot.Analytics is registered in the ecosystem as `dot-analytics`. Dot.Brain's ingested view (linked above) describes a full DKP (Dot Knowledge Pack) integration contract for this platform — a `platform.dkp.json` manifest, four Knowledge Pack types, a KPI-catalog sync job, and a delegated composite "chain view" spanning Farms/Emall/Billing.

**What's actually implemented today vs. what's described there:**

| Brain doc claims | Status in this codebase |
|---|---|
| `platform.dkp.json` manifest, signing key, `publishes`/`subscribes` lists | Not present. No DKP manifest file exists in this repo yet. |
| `analytics.kpi.published/restated`, `analytics.view.created/retired`, `analytics.catalog.synced` events | Not present. Internal events (§4) are Laravel/Livewire broadcast events, not DKP-formatted ecosystem events. |
| KPI-catalog daily sync job diffing against Brain's metric registry | Not present. `MetricDefinition` exists as an internal catalog (55+ seeded metrics) but there is no sync job reconciling it against `brain.metrics.md`. |
| `analytics.view:value-chain:agri` composite view (Farms/Emall/Billing) | Not present. No composite/chain-view concept exists in the current schema (`AnalyticsDashboard`/`DashboardWidget` are single-tenant dashboard building blocks, not cross-platform composite views). |
| Ingestion of pushed platform data, intelligence engines, cross-platform insights, alerts, recommendations | **Implemented.** This is the actual strength of the current codebase (§2, §3). |

Knowledge Packs we intend to publish, once the DKP integration layer is built, map naturally onto what already exists:

| Payload type | Likely source in this codebase |
|---|---|
| `observation` | `ComputedMetric` aggregates, `AnalyticsSnapshot` ingestion volume |
| `insight` | `CrossPlatformInsight` records, especially those tagged `CriticalInsightDiscovered` |
| `outcome` | `ExecutiveBriefing` results, `BusinessDnaRecomputed` confidence deltas |
| `incident` | `AnalyticsAlert` records at high severity |

This mapping is our own proposal, not yet wired to Dot.Brain's ingestion pipeline — it is the concrete next step for closing the gap in the table above.

## 7. Roadmap / Open Questions

- [ ] Reconcile the README's platform roster (Dot.Fleet, Dot.CRM, Dot.Documents, Dot.Hear, Dot.Vault, Dot.Security, Dot.API, Dot.Flow, Dot.Assets — none of which appear in Dot.Brain's current 20-platform ecosystem list) against the actual Dot Ecosystem platform names. Either the README predates a platform-naming pass, or `IntelligenceEngineService::PLATFORMS` needs remapping to real platform IDs (`dot-farms`, `dot-emall`, `dot-billing`, etc.).
- [ ] Build the `platform.dkp.json` manifest and wire the four Knowledge Pack payload types (§6) to real emission points in the codebase.
- [ ] Implement the KPI-catalog sync job against Dot.Brain's metric registry (`brain.metrics.md`), including the drift-count alarm described in Dot.Brain's ingested view §7.
- [ ] Decide whether the "chain view" / composite-view concept (Farms→Emall→Billing) becomes a new model (`CompositeView`?) or is expressed through existing `AnalyticsDashboard`/`DashboardWidget` — currently no schema supports cross-platform composite assemblage.
- [x] Clean up the duplicated `v1` route group in `routes/api.php` — done 2026-08-01: the second, unreachable block (shadowed by the first, identically-prefixed group) was removed; no route behavior changed since Laravel matches the first-registered route.
- [ ] Extend multi-tenancy model to the cross-tenant, floor-inheriting aggregation described in Dot.Brain's ingested view §7, if/when composite views are built.

## 8. Security Review Findings (Deep Pass — Intelligence Engine Internals)

Dot.Brain's `os/15-MEGA-v2.md` flagged Dot.Analytics as `S=1` — the first platform-loop pass deliberately stayed out of the 17-engine intelligence service, knowledge graph, Business DNA profiling, and ingestion pipeline. This section documents the dedicated follow-up hand-review of exactly those internals (2026-08-01), since no PHP/Composer/Postgres was available to run anything — read-only code audit.

**Cross-tenant data isolation (highest-value check for this platform):** every intelligence-layer query that touches team data was checked — `CrossPlatformIntelligenceService::runForTeam()`, `KnowledgeGraphService` (nodes/edges/traverse/BFS/explainEntity), `BusinessDnaService::computeForTeam()`, `IntelligenceEngineService::buildEcosystemContext()`, `ReportGenerationService`'s four data providers, and the `IntelligenceController`/`SavedReportController`/`ReportController`/`IngestController` endpoints. All of them filter by `team_id` at the query root, and `IntelligenceEngineService` itself (the "17-engine" registry) turned out to hold no database queries at all — it's a static platform/engine catalog, so there's no join surface there to leak across teams. No cross-tenant join or unscoped aggregate was found anywhere in this pass. This is the strongest finding of the whole review: the isolation model held up under a much closer look than the first pass gave it.

**Business DNA profiling:** `BusinessDnaProfile` is looked up/created via `firstOrNew(['team_id' => $team->id])` and every signal it's built from (`AnalyticsSnapshot`, `dataSources()`) is queried through `$team`. Clean.

**Reports/briefings IDOR:** every by-ID lookup (`SavedReportController::run/runs/destroy`, `AnalyticsReport::findOrFail`, `routes/web.php`'s `/reports/{id}/download`) is wrapped in `->where('team_id', $team->id)->findOrFail($id)` — the same class of IDOR fixed in Dot.Billing/Dot.Ehail does not exist here. `ExecutiveBriefingPanel` and `CrossPlatformInsightPanel` (Livewire) are likewise team-scoped, and `CrossPlatformInsightPolicy` correctly checks `$insight->team_id` on view/update/delete.

**Feature flags — FIXED:** `GET /api/v1/feature-flags` (`FeatureFlagController::index()`) had no `Gate::authorize` check (unlike `store`/`enable`/`disable`/`rollout`, which are all correctly gated to `manage-platforms`) and returned every flag's raw `enabled_for_teams` / `enabled_for_users` targeting arrays to any authenticated user of any team — a real cross-tenant metadata leak (team/user ID enumeration across the whole ecosystem). Fixed in `app/Http/Controllers/Api/V1/FeatureFlagController.php`: non-admins now get a boolean (`enabled_for_current_team`/`enabled_for_current_user`) instead of the raw arrays; admins (`manage-platforms`) still see full targeting data. Commit `fd750ef`.

**Ingestion pipeline:** `IngestController::receive()` requires an authenticated Sanctum user, resolves the `DataSource` strictly via `where('team_id', $team->id)`, and verifies an HMAC-SHA256 signature (`hash_equals`) when a webhook secret is configured. `IngestPlatformSnapshotJob` re-derives `team_id` from the `DataSource` record server-side (never trusts the payload for tenant identity) and stores the payload as an opaque JSON blob — no raw SQL construction from ingested data, so malformed input from one source cannot reach another tenant's rows. One soft spot, not fixed (judged out of scope for a small isolated fix): if a `DataSource` is created without a `webhook_secret`, `receive()` silently skips signature verification and accepts unauthenticated-payload — still requires a valid Sanctum token for that team, so it is not a cross-tenant hole, just weaker origin authentication than intended when a team forgets to configure a secret.

**Not modified / too deep to safely touch in this pass:** the knowledge graph's BFS traversal/scoring algorithms, the 17-engine registry's engine-to-platform mappings, and the AI-prompt-driven insight generation in `CrossPlatformIntelligenceService::generateInsightsForEngine()` (per this pass's explicit scope boundary) — no correctness or security concern was found in these during read-through, but their internal algorithmic logic was not modified, consistent with instructions.

## Change Log

| Version | Date | Author | Change |
|---|---|---|---|
| 1.2.0 | 2026-08-03 | Sakhile Bhayi | First real-execution verification pass against a live PHP/PostgreSQL toolchain (this codebase had never been run before). `composer install` succeeded on stock PHP 8.5 (no version downgrade needed, no phpspreadsheet-style ceiling in this repo's dependency tree). A fresh `php artisan migrate` against an isolated Postgres database ran clean end-to-end on the first try — no ordering bugs, no driver-specific SQL, no missing tables — and `php artisan test` passed clean on the first try too: 490 tests, 483 passed, 7 skipped, 0 failed (1062 assertions). No real bugs found or fixed; this platform's migrations and test suite were already correct against a real database. Applied the Dot.Brain ADR-0013 idempotent guard (`Schema::hasTable`/`hasColumn` checks) to this platform's six shared Jetstream-core migrations so they're safe to run in any order against the shared `infodot` database alongside every other Dot platform; re-ran migrate and the full test suite afterward on a fresh database and got the identical 490/483/7/0 result, confirming the guard is behavior-neutral. |
| 1.1.0 | 2026-08-03 | Sakhile Bhayi | Redesigned `resources/views/welcome.blade.php`'s marketing surface: the nav's flat indigo square with a "D" glyph and the footer's matching small square are now the real `public/images/logo.png` lockup. The hero section (previously a plain `bg-slate-950` fill with a gradient headline, no photography) now has a real photographic background: an analytics-dashboard-on-a-laptop-screen photo by Luke Chesser (@lukechesser), unsplash.com/photos/graphs-of-performance-analytics-on-a-laptop-screen-JKUTrJ4vK00, hotlinked via Unsplash's CDN (`images.unsplash.com/photo-1551288049-bebda4e38f71`), under a dark slate gradient overlay tuned for WCAG-adequate text contrast. Verified the image URL resolves with `curl -sI` (HTTP/2 200) before committing. Left the pre-existing duplicate stock-Laravel-scaffold markup appended after this file's closing `</html>` tag untouched — it predates this pass and is out of scope for a bounded visual-only change. |
| 1.0.0 | 2026-08-01 | Analytics Platform Lead | Initial wiki, derived from the actual codebase (routes, models, migrations, services) with explicit gap analysis against Dot.Brain's ingested view |
| 1.0.1 | 2026-08-01 | Analytics Platform Lead | Platform-loop pass: real logo wired into favicons/nav/auth pages; removed leftover `dot_projects.png` asset and the dead default-Jetstream `components/welcome.blade.php`; `composer.json` name fixed from `laravel/laravel` to `dot/analytics`; README's top-level platform roster diagram corrected to the real 20-platform Dot Ecosystem list (the README's per-engine "Primary Sources" table and `IntelligenceEngineService::PLATFORMS` still use the earlier fictitious roster — left alone, flagged as a roadmap item since remapping it touches core intelligence-engine logic); added Feature tests for reports/metrics/platform-catalog pages; no engine/knowledge-graph/DNA code touched. |
| 1.0.2 | 2026-08-01 | Analytics Platform Lead | Deep security pass on intelligence-engine internals (§8), the follow-up to the `S=1` caveat in Dot.Brain's `15-MEGA-v2.md`. Cross-tenant isolation across the 17-engine service, knowledge graph, Business DNA, and reports/briefings held up clean. Found and fixed one real cross-tenant leak: `GET /api/v1/feature-flags` exposed other teams'/users' flag-targeting arrays to any authenticated user (commit `fd750ef`). Knowledge-graph traversal algorithms and engine prompt logic were read but not modified. |

## Open Questions

- Is the README's ecosystem platform roster stale, or does `IntelligenceEngineService::PLATFORMS` intentionally support a broader/different set of integrations than the current 20-platform Dot Ecosystem?
- Who owns building the DKP manifest and sync job — the Analytics team, or a shared Dot.Brain integration effort applied uniformly across platforms?
- Should `CrossPlatformInsight` become the direct source object for `insight`-type Knowledge Packs, or does it need a translation layer first (W5 provenance, confidence scoring per Dot.Brain's schema)?
- Should `DataSource` creation require a `webhook_secret` before a platform is marked `connected`, so `IngestController::receive()` can never silently accept unsigned payloads? Currently valid Sanctum auth is still required, so this is a hardening item, not a cross-tenant vulnerability — worth a follow-up pass.
