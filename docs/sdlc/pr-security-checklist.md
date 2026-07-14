# Per-Code-Change Security & Quality Checklist

**Use this on every PR before merging.**
**Copy it into `.github/pull_request_template.md` to enforce it on all contributors.**

---

## Summary of Change

Brief description:

Ticket / Issue:

---

## 1. Tenant Isolation

- [ ] Every new query includes `where('team_id', ...)` or uses the `BelongsToTeam` global scope
- [ ] No query returns data across team boundaries
- [ ] New cache keys are prefixed with `team:{id}:`
- [ ] New queue jobs carry `teamId` and validate it before processing

**If adding a new Eloquent model:**
- [ ] `BelongsToTeam` trait applied
- [ ] Policy created and registered
- [ ] Factory created

---

## 2. Authorization

- [ ] New Livewire actions call `$this->authorize('action', $model)` before mutating
- [ ] New API routes have `Gate::authorize()` or `$this->middleware('can:...')`
- [ ] No raw ID accepted from user input without ownership validation
- [ ] Cross-tenant IDOR tested: "can user A affect user B's records?"

---

## 3. Input Validation & Output Encoding

- [ ] All request inputs validated with `$request->validate()`
- [ ] No `$request->all()` passed directly to `create()` or `update()`
- [ ] User-controlled strings displayed in Blade are escaped (`{{ }}`, not `{!! !!}`)
- [ ] API responses don't expose `config`, `password`, or internal fields

---

## 4. Authentication & Secrets

- [ ] No hardcoded secrets, tokens, or passwords
- [ ] No `auth()->user()` (use `Auth::user()` facade — avoids Intelephense errors)
- [ ] API keys read from `config('services.*')` — never from `env()` directly in code
- [ ] New environment variables documented in `.env.example` with a comment

---

## 5. AI Calls

- [ ] All AI prompts pass through `AiModelRouter::complete()` (never raw HTTP)
- [ ] PII scrubbed before prompt assembly (use `PiiScrubber` service)
- [ ] AI response validated against expected schema before persisting
- [ ] AI capability string matches an entry in `AiModelRouter::CAPABILITY_TIERS`

---

## 6. Connectors & External HTTP

- [ ] User-supplied URLs pass through `assertNotSsrf()` before any HTTP request
- [ ] No `curl_exec()` or `file_get_contents(URL)` outside of the Connector layer
- [ ] Webhook payloads validated with HMAC + timestamp before processing

---

## 7. Database

- [ ] No raw SQL queries (`DB::statement`, `DB::select`) — use Eloquent/QueryBuilder
- [ ] New migrations are reversible (`down()` method implemented)
- [ ] New columns have appropriate defaults or are nullable
- [ ] No `eval()` anywhere

---

## 8. Events & Jobs

- [ ] New domain events dispatched via `Event::dispatch()` not `new Event()`
- [ ] New jobs have `$tries` and `$timeout` defined
- [ ] New jobs implement `$this->middleware()` returning `[new EnsureTeamExists()]`
- [ ] Listeners that should run async implement `ShouldQueue`

---

## 9. Tests

- [ ] Tests written for the happy path
- [ ] Tests written for at least one invalid/edge input
- [ ] Tests written for authorization (authorised + unauthorised)
- [ ] No test uses `$this->assertTrue(true)` as its only assertion
- [ ] Coverage did not decrease: Before ___% → After ___%

---

## 10. Code Quality

- [ ] PHPStan passes: `vendor/bin/phpstan analyse app --level=5 --no-progress`
- [ ] PHP syntax valid: `php -l <changed files>`
- [ ] No `TODO`, `FIXME`, or `HACK` comments in production code
- [ ] No commented-out code blocks
- [ ] New services registered in `AppServiceProvider`
- [ ] New commands registered in `routes/console.php`

---

## Scorecard Impact

Which scorecard domains does this change affect?

- [ ] IAM
- [ ] Authentication
- [ ] Authorization
- [ ] Multi-Tenancy
- [ ] Data Protection
- [ ] API Security
- [ ] Application Security
- [ ] Infrastructure
- [ ] Secrets
- [ ] Logging & Audit
- [ ] Monitoring
- [ ] Compliance
- [ ] Secure SDLC
- [ ] AI Governance
- [ ] None

Update `docs/enterprise-security-scorecard.md` if any required actions are completed.
