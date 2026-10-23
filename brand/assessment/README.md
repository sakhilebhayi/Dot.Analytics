# Brand experience baseline: Dot.Analytics (business-intelligence dashboard demonstration)

> **Advisory. Written by an AI reviewer. No profile is ratified.** This is a first baseline from the Brand Feeling Engine, run on the Dot.Analytics business-intelligence dashboard (framed that way for this demonstration; its own README calls the product an intelligence layer, not a BI tool). Nothing here blocks a merge or a deploy, nobody at the company has reviewed it yet, and the brand profile it is measured against is a draft that the owner has not ratified. Scores are honest and low on purpose: unmeasured checks count as zero and are listed, and a product cannot be `release_ready` without observed or customer evidence that does not exist yet.

| | |
|---|---|
| **Evidenced score** | **19.04 / 100** |
| **Ceiling** (most this evidence could earn) | 62.20 |
| **Attainment** (evidenced / ceiling) | 30.62 % |
| **State** | `not_ready` |
| **Confidence** | Low (Evidence disagrees by more than 1 level(s) on 3 dimension(s).) |
| Blockers | 0 |

Read the three numbers together. The ceiling is 62.20 because only automated checks and expert assessment exist; the other 37.8 points can only be earned from observed or customer evidence. An AI-only expert assessment counts at most half. Every check that was not run counts as zero and is listed below.

**Blockers recorded by the engine:**
- none

| Dimension | Status | Points earned / weight | Ceiling (this evidence) | AI expert level (0-4) |
|---|---|---|---|---|
| Emotional clarity and positioning | partially evidenced | 1.18 / 8 | 4.00 | 2 |
| Brand distinctiveness | partially evidenced | 1.14 / 7 | 4.20 | 2 |
| Usability and cognitive load | partially evidenced | 2.75 / 12 | 7.20 | 2 |
| Visual and interaction craftsmanship | partially evidenced | 0.43 / 9 | 7.20 | 1 |
| Functional completeness | partially evidenced | 6.38 / 10 | 9.00 | - |
| Trust and transparency | partially evidenced | 2.22 / 10 | 7.00 | 2 |
| Perceived and demonstrated value | not measured | 0.00 / 8 | 4.00 | - |
| Accessibility and inclusion | partially evidenced | 2.92 / 12 | 8.40 | 1 |
| Cross-platform consistency | partially evidenced | 0.43 / 6 | 4.80 | - |
| Reliability and recovery from errors | partially evidenced | 1.60 / 8 | 6.40 | - |
| Retention through continuing value | not measured | 0.00 / 5 | 0.00 | - |
| Measurable customer outcomes | not measured | 0.00 / 5 | 0.00 | - |

Dimensions 11 and 12 (retention through continuing value, measurable customer outcomes) are **not measured**: they accept only real observed or customer results, and none exist. Perceived and demonstrated value (dimension 7) has no automated or expert evidence in this run.

## Main gaps found

1. **Focus and labels.** 15 inputs and buttons remove the browser outline with no replacement; 19 of 26 form fields have no programmatic label (WCAG 2.4.7, 1.3.1, 4.1.2).
2. **Small text and low contrast.** 116 functional text elements render under 11 px and 23 body-text elements under 12 px (mono labels set at 0.55 to 0.7 rem); placeholder text is 3.6:1; the role list on team settings is grey 2.2:1 on light.
3. **Phone width.** The 264 px sidebar never collapses; at 375 px the dashboard content is about 110 px wide. Team and profile pages (Jetstream defaults) also look unthemed inside the dark shell.
4. **Reduced motion.** 3 of 29 motion declarations are covered by `prefers-reduced-motion`.
5. **Honest claims.** The welcome page says "17 intelligence engines"; the dashboard says 13 can run (4 are dormant by design). The sign-in page offers "Get started free" although public sign-up is closed and the link returns to the sign-in page. The app layout loads the Tailwind CDN script, an avatar service and fonts from third parties at run time.
6. **No DESIGN.md or PRODUCT.md** records the identity, so brand distinctiveness cannot reach the top level.

What is already good: every seeded tile says DEMO DATA, freshness ("last snapshot received") is shown, platforms that are not connected are listed instead of hidden, a custom 404 and 500 page with a next step exist, and 1,086 tests pass.

## Findings that are probably false positives (do not chase these)

- `d5.dead_links` (2): both are `route('contact')` inside `@if (Route::has('contact'))`; the engine does not read the guard.
- `d6.privacy_terms_present` ("no privacy notice page"): the privacy policy, terms and cookie pages exist and render (Jetstream registers `/privacy-policy` and `/terms-of-service` from `resources/markdown`); the engine does not see package-registered routes.
- `d5.route_inventory_vs_claims` (4): matches README bullets to route names by keyword; noisy.
- The detector checks `d2.impeccable_detect_findings` and `d4.detector_clean` both read 0.00 because the engine counts hits against a fixed ceiling of 10; 11 pages of snapshots saturate it.

## How this baseline was produced

- **Engine.** Dot.Brain brand engine 0.2.0 (Dot.Brain commit `2019312`), commands `analyze`, `evidence assemble`, `score`, `plan`. The engine reads files and reports, runs no tool itself and calls no network. Rubric BXQS 1.0.0 (sha256 `f5a4f8cb49b8`, **proposed, not ratified**). Profile `dot-ecosystem@0.1.0` (**draft, not ratified**): no product profile exists for Dot.Analytics yet, so the ecosystem profile is used.
- **Checkout.** Commit `b547e8b` of the repository's default branch `feature/ecosystem-sso` (`origin/main` last moved on 2026-06-29 and the default branch holds 110 commits it does not, so `main` was not used). The checkout was analysed untouched.
- **Run locally, not against any live host.** A separate clone, Laravel's own test kernel, SQLite `:memory:` only, a `.env` holding only the app key and the sqlite database lines, `PAO_DISABLE=1`, no MySQL or Postgres, no outbound requests. The synthetic data comes from the repository's own `DemoMetricsSeeder` (invented numbers; every tile is labelled DEMO DATA). The repository's own PHPUnit suite was also run on SQLite `:memory:` and its JUnit file was given to the engine: 1,093 tests, 1,086 passed, 0 failed, 7 skipped.
- **Rendered-page evidence.** 11 pages (welcome, sign-in, cookies, terms, privacy, 404, dashboard, search, profile, team settings, team create) were rendered to HTML, served from a local static server and opened in headless Chrome. 45 external references (Google Fonts, icon font, avatar service, Tailwind CDN script) were removed so nothing leaves the machine; the Tailwind CDN script was replaced by an equivalent stylesheet built locally, and icon-font glyph text was hidden. The design detector (`impeccable detect`, v0.1.11) ran on the source (11 hits) and on the snapshots (191 hits; 5 script errors caused by the removed scripts and 4 contrast hits with an unresolved background were dropped, 182 kept). Responsive overflow was measured with Playwright at 375, 768 and 1280 px (not scored; the engine has no adapter for it): the dashboard overflows by 78 px, search by 26, profile by 73, team settings and team create by 105 at 375 px; nothing overflows at 768 or 1280.
- **Not installed, so not measured:** axe-core and Lighthouse (no global tool was installed for this review). No assistive-technology test, no user session and no customer feedback exist, so dimensions 11 and 12 and every observed or customer metric are **not measured**.
- **Expert assessment.** Written by an **AI reviewer** (assessor `ai-reviewer-fresh-2026-10-23`, kind `ai`) that did not build this product in this run. It is AI-only expert evidence: the rubric counts it at no more than half. `built_by` is declared as `prior-ai-sessions-and-owner`; independence is declared, not proven. No user was observed and nobody was asked how they feel.

## What is not measured and why

| Automated check | Why it is not measured |
|---|---|
| `d1.product_md_consistency` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d1.purpose_statement_present` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d10.error_copy_lint` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d10.error_path_tests` | the repository does not tag tests as error-path tests, so the test adapter found none. |
| `d2.token_uniqueness` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d3.primary_action_per_screen` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d3.task_flow_step_counts` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d4.responsive_overflow` | the engine has no built-in check or adapter for it. The reviewer measured horizontal overflow separately (see the rendered-page paragraph above) but it is not scored. |
| `d4.spacing_type_scale_conformance` | the check ran and said it could not judge |
| `d6.consent_handling` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d6.honest_states` | the check ran and said it could not judge |
| `d7.empty_state_guidance` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d7.time_to_first_result` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d7.value_above_fold` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d8.axe_violations` | axe-core is not installed on the review machine and no global tool was installed, so no axe report exists. Not measured. |
| `d9.principle_diff` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d9.shared_components` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |

## Unknowns

- No product profile exists, so audience, purpose and intended feelings for Dot.Analytics are not recorded; the ecosystem profile is a draft with open questions that block ratification (shared token format, glossary, whether the control-room look is an ecosystem direction).
- Whether the "17 engines" and "recomputed nightly" wording is intended marketing or a count that should follow what runs is the owner's call.
- Nothing is known about how real users feel, whether they can read the dashboard on a phone-sized screen they actually use, or whether assistive technology can operate it.

## Files in this folder

| File | What it is |
|---|---|
| `2026-10-23-b547e8b.json` | the BXQS score report (`score` output) |
| `2026-10-23-b547e8b.evidence.json` | the evidence file the score was computed from (`evidence assemble` output; includes the AI assessments) |
| `2026-10-23-b547e8b.analysis.json` | the analysis (`analyze` output); it is the baseline that `brand-engine verify --before` needs |
| `2026-10-23-b547e8b.plan.md` | the ordered task list (`plan` output). Tasks that point at `rendered-snapshots/...` refer to pages rendered for this review, not files in this repository |

Re-score: `node services/brand-engine/src/cli.js score --evidence 2026-10-23-b547e8b.evidence.json --profile dot-ecosystem --built-by prior-ai-sessions-and-owner` from a Dot.Brain checkout at or after the commit above.
