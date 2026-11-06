# R-38 acceptance: the 25 checks in section 19, and what proves each

`tests/Feature/Acceptance/AcceptanceTest.php` runs the application end to end: real routes, a real database, collected and
stored data, rendered pages. The number in each test name is the item's number in the brief. Where an item is about a real
browser, the crawl recorded in `R38_READINESS.md` is the proof and the test guards against regression.

| # | Check | Evidence |
|---|---|---|
| 1 | A new visitor can access the public experience | `AcceptanceTest::test_01`; `PublicPagesTest` (no cookie, strict policy, every page renders on an empty library) |
| 2 | Meaningful intelligence without registering | `test_02`; `PublicPagesTest::test_a_figure_carries_its_source_period_licence_and_every_value` |
| 3 | Industry pages display the correct information | `test_03` (an industry shows its own series and no other's); `DerivedTest` for filing by word lists |
| 4 | Geographic filters return accurate results | `test_04` (indicator `?place=`, country page, newsroom `place` filter) |
| 5 | Items link to their original sources | `test_05`; `PublicPagesTest::test_a_development_links_to_the_original_and_adds_nothing_it_may_not` |
| 6 | Data freshness is visible | `test_06`; `PublicPagesTest::test_an_old_reading_is_called_out_of_date_with_its_date_and_never_current` |
| 7 | Search returns relevant results | `test_07` (headline ranks above a mention), `PublicPagesTest` (whole-word matching, grounded answers, nothing invented) |
| 8 | Users can explore related information | `test_08` (comparison table, place links, related developments) |
| 9 | Charts display accurate underlying data | `test_09` compares every chart readout with the stored value and checks the table alternative |
| 10 | Report details are accessible | `test_10` (working tape, licence, CSV with credit line, method page) |
| 11 | Register and authenticate through InfoDot | `test_11` (sign-in goes to InfoDot, local passwords refused); `EcosystemSsoTest`, `InfoDotRedeemTest`, `InfoDotTeamSyncTest` |
| 12 | Save and personalise | `test_12_and_13`; `MyLibraryTest` (watch, save, preferences, export, erase) |
| 13 | Watchlists and alerts function correctly | `test_12_and_13`; `MyLibraryTest` (alert once, only for what is watched, movements, retention, digest rules) |
| 14 | Private analytics remain private | `test_14`; `PublicCannotReachPrivateTest` (every private model fails closed for a guest); `MyLibraryTest` (people are isolated) |
| 15 | InfoDot discovery respects visibility and verification | `test_15_and_16`: the library never names or lists a business; it links into InfoDot's own directory search, which enforces each business's visibility and verification. The verified-only link passes `verified=1` |
| 16 | Business profile links work | `test_15_and_16`, with a limit stated below |
| 17 | Ecosystem links lead to real, available capabilities | `test_17` (only platforms that answered a check in the last three days); `SiteMeasuresTest` (the redirect cannot be abused) |
| 18 | Ingestion handles duplicates | `CollectionTest` (same data twice changes nothing, revisions kept, feed de-duplication) |
| 19 | Failed ingestion recovers safely | `CollectionTest` (a failing source keeps what it had and backs off, Retry-After honoured, a long run resumes); `DerivedTest` (issues raised once and cleared when fixed) |
| 20 | Stale or unavailable data is communicated | `test_06`, `PublicPagesTest` (sources page shows failures, out-of-date and not-available states) |
| 21 | Pages perform at a realistic volume | `test_21` loads over 30,000 readings and 1,500 developments and holds every page to a query budget and a time limit |
| 22 | Mobile and tablet layouts work | `test_22_and_23` (viewport, breakpoints, reduced motion) plus the phone and tablet crawl in the readiness report |
| 23 | Accessibility and keyboard navigation | `test_22_and_23` (skip link, landmarks, one h1, labels, alt text) plus axe and the keyboard pass in the readiness report |
| 24 | Permissions enforced on the server | `test_24`; `ViewerRoleMatrixTest`, `HardeningTest`, `MyLibraryTest` |
| 25 | No fake statistics, placeholder integrations or fabricated intelligence | `test_25` (an empty library shows no number; no placeholder text in the views; every source has a licence and credit line; every series has a definition and unit) |

## What the tests cannot prove, said plainly

- **16, business profile links.** InfoDot has no business API or feed, only its public directory pages. The library therefore does not
  list or name individual businesses, so it cannot link to one profile. It links to the directory filtered by sector. Showing
  specific businesses needs an InfoDot endpoint that returns only public, verified businesses.
- **11, the live sign-in.** Tested against this application. The live round trip with InfoDot's identity provider happens on the real
  host and is part of the post-deploy check.
- **22 and 23** are proved by a real browser run, not by the unit tests.
- **Mail.** The digest is built and queued correctly; delivery depends on mail settings on the host.
