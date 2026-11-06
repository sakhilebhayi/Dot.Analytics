# R-38 Dot.Analytics: implementation plan and decisions

Brief: `requirements/dot-analytics-transformation.md`. Research: `R38_RESEARCH.md`. Written 2026-11-06 from a code audit of `f035dac`, a security audit, and a data-rights study. The audits themselves are kept off this public repository.

## What the audit found, in six lines

1. The core is honest: signed snapshot to computed metric to anomaly alert to tile. It stays, as the private "Workspace".
2. There was no public intelligence at all: no model, no source, no route.
3. Nothing flows into the private side in production, because the Dot platforms have no `ANALYTICS_INGEST_URL`. The workspace will be empty until the owner sets those secrets.
4. The tenant scope failed open, which is the one thing that could have turned public pages into a leak. Fixed in A0 (#42).
5. Unsafe code that nobody could reach (natural-language SQL, connectors, pipelines) is removed. The knowledge graph and feature flags did nothing and are removed.
6. The host is shared cPanel with SQLite, a cron worker that runs under a minute, no Node, no daemon. That shapes everything below.

## Decisions, and why

| # | Decision | Why |
|---|---|---|
| D1 | Public intelligence has its own store (`intelligence` connection, no `team_id`, own SQLite file in WAL, MySQL by setting) | A long import must never block a sign-in, and public code must never be able to reach company data. A fault in one cannot touch the other |
| D2 | Treat the site as commercial from day one | It exists to bring businesses to InfoDot. Sources limited to non-commercial use are out now, not later |
| D3 | Launch as a **primary-source newsroom** and an indicator library, not a news aggregator | Almost no publisher allows reuse, and an RSS feed gives no rights. Central banks, regulators, filings and statistics offices do |
| D4 | Four connector kinds, sources are configuration: REST JSON, SDMX/CSV, file, RSS/Atom | One pattern serves OECD, ECB, BIS, ILO and others |
| D5 | Licence is data. Every source row carries licence, attribution, what may be summarised and whether paid use is allowed. Pages render attribution from it | Brief 12 and 14: nothing is sold or summarised that we have no right to |
| D6 | Never ingest FRED, FAO, WHO, IEA datasets, LBMA, JSE, Yahoo. SARB, gov.za, IMF, WTO, the Pink Sheet and Eskom wait for written permission and are listed as owner letters | The licence readings in the research |
| D7 | Every figure and item carries one of four labels: **source fact**, **calculated by us**, **AI summary**, **editorial view**. A forecast is a fifth, with assumptions | Brief 8 and 14. Facts, interpretation and forecasts are never mixed |
| D8 | Search is a plain, explainable scoring over our own tables, as InfoDot does it. Questions in words are answered only from stored figures and items, in code, with links. A model may phrase an answer only where a summary is allowed. If we have no figure, the answer says so | Brief 5: no confident answers from missing data |
| D9 | Summaries are generated only for text we hold rights to, stored with model, source and time, and shown labelled. They are off until a provider key and cap exist | No AI key has been set, and the cost cap is the owner's call |
| D10 | Public pages are cached whole and cost the database nothing for a repeat visitor | Shared host. Page cache, not live queries |
| D11 | The workspace (signed in) keeps its role-based teams and gains personal watchlists, saved searches and alerts per person, in the private store | Personalisation is a person's, not a company's |
| D12 | Public analytics about the site itself are first-party, no cookies that identify a person, counted in our own tables | POPIA, and the brief's measures of real outcomes |
| D13 | Ecosystem links only to platforms that answered 200 on 2026-11-06, and the InfoDot directory only through its public pages | Brief 9: nothing fake. InfoDot has no business JSON API, so discovery links out to its public search by sector and place |
| D14 | Premium: public information stays free forever. A paid tier is designed but not switched on, because no billing exists here and no price may go on data we cannot sell | Brief 12 |
| D15 | Design: an editorial reading surface that is not any other Dot platform. Light cool-white ground, ink text, one blue, IBM Plex Sans and Mono, a provenance line under every figure, a coverage grade on every page | The audit's own advice: the brief wants a reading product, and near-black plus one bright accent is on the owner's reject list. Built with the awwwards skill, checked with the Impeccable detector |

## Phases

| Phase | Work | Brief section |
|---|---|---|
| A0 | Fail-closed scope, ownership, unsafe code out, AI caps, CORS, SQLite waits | 15, 16. **Merged #42** |
| B1 | Intelligence store, collection engine, source health, freshness, issues | 14, 15 |
| B2 | Fetchers (World Bank, SDMX/OECD/BIS/ECB, Stats SA ASCII, EIA, Pink Sheet gated off, RSS/Atom, EDGAR) and the launch source set with its licences | 4, 7, 15 |
| B3 | Classification of items into industries and places, by rules we can show, never by a model alone. Signals computed from numbers | 5, 8, 14 |
| C1 | Design system and the public shell | 13 |
| C2 | Home, industries, countries, indicators, newsroom, briefings, sources and coverage pages | 4, 6, 7 |
| C3 | Search and the grounded question box | 5 |
| C4 | Charts: one component, every figure with its provenance, a table alternative | 6 |
| D1 | InfoDot discovery and ecosystem map from verified facts | 9 |
| E1 | Workspace redesign: split the single page into real routes, mobile shell | 10, 13 |
| E2 | Watchlists, saved searches, preferences, alerts, briefing email through the existing notification path | 10, 11 |
| E3 | Premium design and entitlements groundwork | 12 |
| F1 | Site measures, internal performance view, source health view | 17 |
| F2 | Retention, export, erasure, audit trail | 16 |
| F3 | The 25 acceptance tests, accessibility, performance, report, deploy | 19 |

## What cannot be finished by building alone

- Letters for SARB, GCIS, the World Bank (Pink Sheet), the IMF, Stats SA (to confirm and agree automation) and Eskom.
- An AI provider key and a monthly cap. Until then there are no generated summaries and the question box answers only from stored figures.
- `ANALYTICS_INGEST_URL` and the secret pairs on the Dot platforms, so the workspace has data.
- The host: disk quota, whether MySQL is available, and the `INTEL_DB_*` settings.
- Privacy and terms wording needs a lawyer.

## Where it stands (2026-11-07)

Phases A0, B1, B2, C, D1, E1, E2, E3 (groundwork), F1, F2 (for the library and the main audit items) and F3 are built and merged. B3 company pages
are not built. See `R38_READINESS.md` for evidence, what is not done and the owner's list, and `R38_ACCEPTANCE.md` for the 25 checks.
