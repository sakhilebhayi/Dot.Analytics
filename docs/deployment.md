# Deploying Dot.Analytics (shared cPanel host)

Dot.Analytics runs on the same cPanel account as the other Dot apps, on SQLite to start with. The code is
portable (the suite runs on SQLite); PostgreSQL is only used by the optional data-source connectors
(`AiSqlService`, `DatabaseConnector`) that read the *customer's* databases, not by Analytics itself.
Docker and `docker-compose.yml` are for local development only.

**Branch.** Dot.Analytics has no `main`. Its trunk and default branch is `feature/ecosystem-sso`, and that is the
branch the host runs (`DEPLOY_BRANCH` in `.github/workflows/deploy.yml`). Start every run from that branch.

## One-time setup (owner)

1. The cPanel subdomain `analytics.infodot.co.za` must exist with docroot `~/analytics.infodot.co.za`
   (DNS already points at the account).
2. Repository secrets (from InfoDot: `tools/set-deploy-secrets.sh analytics`): `DEPLOY_HOST`, `DEPLOY_USER`,
   `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_PATH` = `/home/<user>/analytics.infodot.co.za/analytics`.
3. First install (InfoDot: `tools/prepare-host.sh analytics`): clones the branch, writes a production `.env`
   on SQLite with the shared secrets from `~/dot-ecosystem-secrets.env`, `APP_KEY`, composer packages, the docroot
   `index.php` shim and `.htaccess`.
4. Run the workflow: `gh workflow run deploy.yml --ref feature/ecosystem-sso -f confirm=adopt -f migrate=migrate`,
   then `-f confirm=deploy` for every release after that.

## Layout on the host

```
~/analytics.infodot.co.za/        docroot (index.php shim -> analytics/public/index.php, .htaccess, build -> ...)
~/analytics.infodot.co.za/analytics/   the app = DEPLOY_PATH (artisan, .env, database/database.sqlite)
```

The smoke check requests `/`, `/login`, `/up`, the built CSS and JS, `POST /up` (must be 405) and fails if
`/analytics/.env`, `/analytics/artisan` or `/analytics/database/database.sqlite` are downloadable.

## What the host `.env` needs

- `INFODOT_PLATFORM_KEY=analytics`, `INFODOT_SERVICE_SECRET` (= `ECOSYSTEM_SECRET_ANALYTICS` in InfoDot's `.env`).
- One `ANALYTICS_INGEST_SECRET_<KEY>` per publishing app (auction, billing, design, ehail, emall, farms, hr, notify,
  pulse, tasks, tutor); each app holds the same value as `ANALYTICS_INGEST_SECRET`.
- `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `BROADCAST_CONNECTION=log`.
- `MAIL_*` before relying on email, and `ANTHROPIC_API_KEY`-style AI keys only if you want AI features
  (without a key they are unavailable, never faked).

## Scheduler and queue

The deploy installs two cron lines in the account's shared crontab (marker `# dot-analytics`): `schedule:run`
(metrics hourly, intelligence engines every six hours, briefings, DNA, scheduled reports) and a cron-driven
`queue:work --stop-when-empty`. Without them the dashboards go stale silently.

## Known limits

- Reverb cannot run on shared cPanel; live "engine completed" pushes are off, the notification bell and briefing
  panel poll instead.
- SQLite is single-writer. Move to MySQL/PostgreSQL when ingest volume grows (set `DB_*` in the host `.env`).
- A failure after maintenance mode starts leaves the site down on purpose; fix and re-run, or restore the rollback
  copy and run `php artisan up`.
