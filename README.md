# Dot.Analytics

Business intelligence you can check, and the intelligence layer of the Dot ecosystem.

Two things live here, and they cannot reach each other.

**The public library** needs no account. It holds industries, countries, indicators, a primary-source newsroom and daily briefings, with
search and a question box. Every figure shows its source, its period, its licence and how fresh it is, and opens a "show working" tape
with its definition and every value. Where something is not held, the page says so. It reads its own database and cannot name a company.

**The signed-in side** has two parts. *My library* keeps a person's watchlist, saved items, alerts and an optional email digest.
The *company workspace* shows what a company's own Dot platforms report (daily snapshots pushed from each platform), with alerts,
recommendations, saved reports and dashboards. People sign in through InfoDot. Teams are the tenants.

## Stack

Laravel 13, PHP 8.3+, Livewire 3 for the company workspace, Jetstream and Fortify, Vite. The production host is shared cPanel: SQLite, a
cron worker that runs under a minute, no Node, no Redis, no daemon. Public pages are server rendered Blade with no inline script or
style, and are cached whole in a store of their own.

## Run it

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan intelligence:install      # industries, places, sources, series
php artisan intelligence:collect      # a pass over the sources that are due; run it again to carry on
npm run build && php artisan serve
```

The public library's data lives in `storage/intelligence/intelligence.sqlite` (WAL) unless `INTEL_DB_DRIVER=mysql` and the `INTEL_DB_*`
settings say otherwise. Set `INTEL_USER_AGENT` to a role mailbox you control, and `EIA_API_KEY` to turn the EIA series on.

## Commands

| Command | What it does | Schedule |
|---|---|---|
| `intelligence:collect` | Collects from the sources that are due, files items, finds unusual movements, builds today's briefings | every 10 minutes |
| `intelligence:install` | Installs reference data. Idempotent | on deploy |
| `intelligence:prune` | Deletes week-old visitor hashes, old questions, old run records | daily |
| `library:scan` | Writes alerts for what people watch | every 30 minutes |
| `library:digest daily\|weekly` | Sends the opt-in email digests, never empty | mornings |

## Data rights

A source is collected only when its licence lets a site that may earn money publish it. Sources waiting for written permission are listed
on the public sources page and are never collected. See `docs/programme/R38_RESEARCH.md`.

## Where things are

- `docs/programme/` the brief, plan, research, acceptance map and readiness report for this work
- `DESIGN.md` the design language
- `docs/metrics-contract.md` how a Dot platform pushes its daily snapshot
- `docs/deployment.md` how it is deployed

## Tests

```bash
php artisan test
```

`tests/Feature/Acceptance` runs the 25 end-to-end checks in the brief against the running application.
