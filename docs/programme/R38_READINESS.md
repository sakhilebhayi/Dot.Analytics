# R-38 Dot.Analytics: readiness report

Written 2026-11-07 for the brief in `requirements/dot-analytics-transformation.md`. Plan and decisions: `R38_PLAN.md`. Data rights:
`R38_RESEARCH.md`. Item by item acceptance: `R38_ACCEPTANCE.md`. Nothing here has been deployed.

## In one paragraph

Dot.Analytics was a private dashboard that held no public intelligence at all, and whose tenant scope failed open. It is now two things
that cannot reach each other. The **public library** is a reading product anyone can use without an account: industries, countries,
indicators, a primary-source newsroom, briefings, search with a question box, each figure carrying its source, period, licence and
freshness, with a "show working" tape under it. **My library** and the **company workspace** are signed in: watchlists, saved items,
alerts and an opt-in email digest for the person; the existing analytics for the company, rebuilt in the same design as one overview plus
four pages. The public library has its own database, cannot name a company, and is checked against that in tests.

## What was found, and what happened to it

| Finding | Result |
|---|---|
| Tenant scope failed open: a guest or a person with no team could read every company | Fixed (#42). Fails closed; a test proves a guest reaches nothing |
| A removed InfoDot admin stayed owner; password sign-in bypassed InfoDot | Fixed. InfoDot's owner owns the company; SSO-only mode with re-verification |
| Natural-language SQL, connectors and pipelines could reach arbitrary data | Removed, with their tables |
| No cap on AI spend, no timeouts | Anthropic-only router, timeouts, monthly caps checked before each call |
| CORS reflected any origin with credentials; weak CSP; Tailwind loaded from a CDN | Fixed. The signed-in layout builds its CSS locally; public pages have a strict policy with no inline script or style |
| Audit trail deleted with its team; a member's account deletion removed shared reports | Fixed (#47) |
| Ingest: no per-company cap, unbounded backdating, unthrottled refusals | Cap, age bound and edge throttle (#42, #47) |
| Unbounded report-run growth; scheduled reports could run every minute | At most hourly, newest 30 runs kept |
| API description listed nine routes that do not exist | Regenerated; a test keeps it honest |
| No public intelligence, no source governance, no ingestion health | Built (below) |

## What was built

1. **Intelligence store.** Its own connection (SQLite in WAL, or MySQL by setting), no `team_id`, sources as configuration with licence,
   credit line and what may be summarised. Idempotent collection in batches, revisions kept, backoff that honours Retry-After, a time
   budget that fits the host's short worker, issues raised once and cleared when fixed.
2. **Sources.** World Bank (each series' licence checked, only CC BY without NC published), OECD, ECB, BIS, EIA (off until a key exists),
   and primary-source newsrooms (Federal Reserve, ECB press, European Commission, two GOV.UK bodies, APO Africa). Sources we may not
   use are not collected: SARB, gov.za, IMF, the World Bank Pink Sheet and Eskom wait for written permission and are shown as such.
3. **The library.** 17 industries, 93 places (54 African), a country comparison, every published series, the newsroom, six briefing
   editions built by code, a sources page, search, grounded questions, the method page, the plans page.
4. **Honesty rules, enforced.** Five labels (source fact, calculated by us, AI summary, editorial view, forecast); current, out of date
   and not available differ in shape as well as colour; an empty library shows no number; an answer says what is missing; no model
   writes any answer or summary today.
5. **InfoDot and the ecosystem.** Links go through a counted redirect that builds the address itself. Platforms appear only while they
   answered a check in the last three days. The directory is reached through InfoDot's own search, which enforces visibility and
   verification.
6. **Personal.** Watch industries, countries and single figures; saved developments and searches; alerts written once with a reason;
   opt-in daily or weekly digest, never sent empty, one-click off; download and erase; limits that protect storage and never hide a page.
7. **Measures.** Cookie-free page and visitor counts by a weekly one-way hash, failed searches, outbound clicks, an operations page,
   source health, retention.
8. **Design.** One language for public, personal and company pages. See `DESIGN.md`.

## Evidence

**Tests.** 1,046 tests, 1,039 pass, 7 skipped (as before this work). New in this work: the public pages, My library, site measures,
hardening, workspace pages, the 25 acceptance checks, and the collection and derivation tests, about 130 in all. CI (tests with
coverage, static analysis, dependency audit, Docker build) was green on every merged pull request.

**Real browser, every public page type and the signed-in pages.** Phone (390), tablet (768) and desktop (1440), light and dark:
- Axe: no violations on any page at any size, signed in or out, after the fixes below.
- No horizontal overflow on any page at any size.
- Mobile LCP 0.8 to 1.6 s, layout shift 0, no loader, JavaScript 119 KB gzipped on the heaviest page (the company workspace).
- Keyboard: a skip link first on every page, every focus stop visible, a chart is one stop that reads each point with the arrow keys.
- The run found real problems that were then fixed: a chart with 36 focus stops carrying labels it may not have, headings that skipped
  levels, a header outside any landmark, duplicate search landmarks, a badge unreadable in light mode, a table wider than a phone,
  and a source shown as "not available" while its figures were current.

**Outside design review.** A fresh reviewer given only screenshots and the audit (the awwwards rubric) scored the public pages 6.6, then,
after a round of fixes, 6.85 out of 10. The target was 8 or more, and it was not reached. Its remaining points are about a lack of
a signature layout or moment (the honesty idea lives in components rather than driving the layout), a sign-in page outside the site
shell (only seen if SSO-only mode is off; production redirects to InfoDot), and small items fixed after that pass. The library is
quiet by decision (see `DESIGN.md`): a reading product should hold still.

**Performance at volume.** With over 30,000 readings and 1,500 developments loaded, the front page runs 35 queries (it ran 195), the
countries comparison 5 (it ran 119), a country page 13 (it ran 102); every page is held to a query budget and a time limit in the tests.
Pages are also cached whole for five minutes, with a generation key so a takedown retires every page at once.

**Rehearsed.** The migrations ran on a populated database (foreign key check clean). `route:cache`, `config:cache`, `view:cache` and
`event:cache` all build. Real collection ran against the live World Bank, OECD, BIS and four newsroom feeds (about 50,000 readings). The ECB
sources could not connect from the development machine (its certificate store); the host will show whether they do.

**Merged, not deployed** (trunk `feature/ecosystem-sso`): #42 foundations and security, #43 intelligence store and collection, #44 public library,
#45 My library, #46 site measures, #47 hardening, #48 workspace, #49 acceptance, #50 to #52 polish after outside review.

## What is not done, and why

- **Company pages** (GLEIF and SEC EDGAR connectors, planned as B3). The table and model exist; no connector is written, so no company page is shown.
- **Individual InfoDot businesses.** InfoDot has no business API or feed, only HTML pages and a directory search, so the library links to the directory by sector and never names a business. Showing specific businesses needs an InfoDot endpoint that returns only public, verified ones.
- **AI summaries, editorial views, forecasts.** The labels exist and the pages know how to show them, but none is produced. They wait for a provider key and a spending cap, and for a person to own the editorial voice.
- **A corrections channel.** There is no way yet for a reader to report a wrong figure or filing. A promise of one was removed from the pages. It needs a mailbox or a form with an owner.
- **The company workspace's inline styles.** The panels still use inline styles and scripts, so the signed-in content policy still allows them. Moving to a nonce policy is its own change.
- **Billing.** There is none. Plans are designed, limited to what a person keeps, and not on sale.
- **Stats SA.** Its feed is behind bot protection and is seeded but off. It needs an agreed access route.
- **Forgot-password throttle** per address is the broker's own; with sign-in through InfoDot in SSO-only mode the route redirects there.

## Owner items (what only the owner can do)

1. **Say "Deploy Dot.Analytics".** Nothing has been deployed. The workflow refuses to run from any branch but `feature/ecosystem-sso`.
2. **Written permission requests**: South African Reserve Bank, GCIS (gov.za), World Bank (Pink Sheet licence), IMF, WTO, Eskom. Each unlocks a source listed on the sources page as waiting.
3. **Confirm access for Stats SA** so its feed can be switched on.
4. **A role mailbox** (for example `analytics@infodot.co.za`) for `INTEL_USER_AGENT` and for a corrections channel. No person's address should be in a declared User-Agent.
5. **An EIA API key** (`EIA_API_KEY`, free) to turn the oil series on.
6. **An AI provider key and a monthly cap**, if summaries are wanted. None is called today.
7. **Mail settings on the host** (`MAIL_*`) so the digest can be delivered. Until then it goes to the log.
8. **`ANALYTICS_INGEST_URL` and the secret pairs on each Dot platform** so the company workspace has data. Deferred by the owner until every platform is done.
9. **Host facts**: disk quota, whether MySQL is available (`INTEL_DB_*`), and that the cron worker can run `schedule:run` each minute. The library's database is a second SQLite file; backups taken by the deploy copy it.
10. **Privacy and terms wording** needs a lawyer. The pages describe what the code does (no cookies on public pages, a weekly one-way hash, what My library keeps); they are not legal advice.
11. **Business listing**: if specific InfoDot businesses should appear, an InfoDot endpoint is needed (see above).

## After the deploy: what to check on the live host

1. The deploy run is green, including the new smoke checks (font, `robots.txt`, sitemap, industries page, the intelligence database file not served).
2. `https://analytics.infodot.co.za/` shows the industries (reference data installed) and, after one or two scheduled passes, real figures.
3. `/sources` shows each source with a recent success. The ECB sources failed to connect from the dev machine because of its certificate store: if they fail on the host too, the page and the operations view will say so.
4. `/me/operate` (as an operator) shows readers, runs and issues. `storage/logs` rotates daily.
5. Sign in through InfoDot, watch an industry, and confirm an alert appears after the next collection.
6. Turn the digest on for one account once mail settings exist, and confirm the one-click link turns it off.
