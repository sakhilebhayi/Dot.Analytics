# Dot.Analytics — Enterprise Vendor Security Assessment Scorecard

**Purpose:** Evaluate whether Dot.Analytics meets enterprise vendor security expectations.
**Product:** Dot.Analytics — Enterprise Intelligence Platform
**Version:** feature/ecosystem-sso
**Assessment Date:** 2026-07-14
**Assessed By:** ___________________________
**Next Review:** ___________________________

---

## Overall Rating

| Domain | Weight | Score | Weighted |
|---|---|---|---|
| Identity & Access Management | 10% | 62 | 6.2 |
| Authentication | 8% | 68 | 5.4 |
| Authorization | 8% | 74 | 5.9 |
| Multi-Tenancy Isolation | 8% | 55 | 4.4 |
| Data Protection | 8% | 48 | 3.8 |
| API Security | 8% | 82 | 6.6 |
| Application Security | 8% | 80 | 6.4 |
| Infrastructure & Cloud | 8% | 65 | 5.2 |
| Secrets Management | 5% | 60 | 3.0 |
| Logging & Audit | 5% | 70 | 3.5 |
| Monitoring & Detection | 5% | 58 | 2.9 |
| Compliance & Privacy | 5% | 35 | 1.8 |
| Secure SDLC | 5% | 68 | 3.4 |
| AI Security & Governance | 5% | 62 | 3.1 |
| Business Continuity | 4% | 30 | 1.2 |
| Vendor Readiness Evidence | 3% | 40 | 1.2 |

**Enterprise Security Score: 63.9 / 100**
**Rating: Moderate Risk — Significant gaps must be closed before enterprise production.**

> See `docs/security/`, `docs/sdlc/`, `docs/compliance/`, `docs/infrastructure/` for
> implementation guides for every Required Action below.

---

## 1. Identity & Access Management (Weight: 10%) — Score: 62/100

### Checklist
- [x] RBAC implemented (owner / admin / member via Jetstream)
- [x] Gates for action-level authorisation (7 defined)
- [x] MFA (TOTP via Fortify)
- [x] SSO — Dot Ecosystem (`EcosystemAuthController`)
- [ ] ABAC (attribute-based access control)
- [ ] SSO — OIDC / SAML for enterprise IdPs
- [ ] SCIM 2.0 provisioning
- [ ] Strengthened password policy (complexity + breach check)
- [ ] IP-based distributed brute-force lockout
- [ ] Break-glass admin accounts documented

### Evidence
- `app/Providers/AppServiceProvider.php` — 7 gates defined
- `app/Policies/` — DataSource, CrossPlatformInsight policies
- `app/Http/Controllers/Auth/EcosystemAuthController.php`

### Findings
OIDC/SAML absent. SCIM not implemented. Password policy uses Fortify defaults (8 chars, no complexity). Account lockout is per-IP throttle only — distributed brute-force from multiple source IPs bypasses it.

### Risks
Enterprise customers on Azure AD, Okta, or Google Workspace cannot federate identity without OIDC. Password policy does not meet most corporate security standards.

### Required Actions
- [ ] Implement OIDC via `laravel/socialite` — guide: `docs/security/authentication.md`
- [ ] Build SCIM 2.0 `/scim/v2/Users` endpoint — guide: `docs/security/authentication.md`
- [ ] Strengthen `Actions/Fortify/PasswordValidationRules.php` with `Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()`
- [ ] Add IP-based lockout in `FortifyServiceProvider` using `Limit::perMinute(5)->by($request->ip())`

---

## 2. Authentication (Weight: 8%) — Score: 68/100

### Checklist
- [x] Sanctum API tokens
- [x] Token revocation
- [x] Browser session management (Jetstream)
- [x] MFA (TOTP)
- [ ] Fortify hardened (IP lockout, exponential backoff)
- [ ] OAuth2 / OIDC token flow
- [ ] MFA enforced for privileged roles (currently optional)
- [ ] Passwordless support

### Evidence
- `tests/Feature/AuthenticationTest.php` — login flow tested
- `tests/Feature/TwoFactorAuthenticationSettingsTest.php`
- `tests/Feature/DeleteApiTokenTest.php`

### Findings
Fortify throttle is route-level only. MFA is available but not enforced for team owners or admins. No exponential backoff on failed logins.

### Required Actions
- [ ] Enforce MFA for users with `manage-platforms` gate — block access if MFA not configured
- [ ] Add exponential backoff: first failure = 1s, sixth = 32s
- [ ] Log all auth events (success/failure/MFA) to `audit_logs` — guide: `docs/security/audit-logging.md`

---

## 3. Authorization (Weight: 8%) — Score: 74/100

### Checklist
- [x] Policies: DataSource, CrossPlatformInsight
- [x] Gates: 7 action-level gates
- [x] All API queries scoped to `team_id`
- [x] Cross-tenant isolation tested (`GateTest`)
- [ ] Policies missing: AnalyticsDashboard, DataPipeline, FeatureFlag
- [ ] No IDOR on saved-reports, connectors
- [ ] Global Eloquent scopes (defence-in-depth)

### Evidence
- `tests/Feature/Authorization/GateTest.php` — isolation tests passing
- `app/Policies/DataSourcePolicy.php`, `CrossPlatformInsightPolicy.php`

### Findings
`AnalyticsDashboard`, `DataPipeline`, and `FeatureFlag` models have no policy. A user who discovers an ID for another team's dashboard can interact with it directly via the API.

### Required Actions
- [ ] Create `AnalyticsDashboardPolicy`, `DataPipelinePolicy`, `FeatureFlagPolicy` — guide: `docs/security/authorization.md`
- [ ] Add `TeamScope` global Eloquent scope to all team-owned models — guide: `docs/security/tenant-isolation.md`
- [ ] Add policy authorization to `DashboardBuilderPanel` and `SavedReportsPanel` Livewire components

---

## 4. Multi-Tenancy Isolation (Weight: 8%) — Score: 55/100

### Checklist
- [x] All queries include `team_id` condition (manual)
- [x] Broadcast channels are private and team-scoped
- [x] Jobs carry `teamId` payload
- [ ] Global Eloquent scopes (no accidental cross-tenant query)
- [ ] Cache keys prefixed per team
- [ ] Queue tenant validation middleware
- [ ] Storage isolation
- [ ] Search index isolation

### Evidence
- `routes/channels.php` — `team.{id}.intelligence` private channel
- `app/Jobs/Analytics/RunIntelligenceEngineJob.php` — `teamId` checked before execution

### Findings
Tenant isolation depends entirely on developers remembering to add `where('team_id', ...)`. One missed scope = data leak. Cache keys are not team-prefixed. Queue jobs have no validation that `teamId` still exists before processing.

### Required Actions
- [ ] Create `TeamScoped` global scope trait — guide: `docs/security/tenant-isolation.md`
- [ ] Implement `TeamAwareCache` service wrapping Cache facade with `team:{id}:` prefix
- [ ] Add `EnsureTeamExists` job middleware that discards orphaned jobs
- [ ] Add `HasTeamScope` to all team-owned models

---

## 5. Data Protection (Weight: 8%) — Score: 48/100

### Checklist
- [x] HSTS in production (`SecurityHeaders` middleware)
- [x] `DataConnector.config` encrypted at rest (`encrypted:array`)
- [ ] Encryption at rest for `audit_logs`, `analytics_snapshots`, `cross_platform_insights`
- [ ] PII identification and masking before AI prompt submission
- [ ] Key rotation policy
- [ ] Backup policy and tested restore
- [ ] Data retention enforcement
- [ ] GDPR/POPIA right to erasure

### Evidence
- `app/Models/DataConnector.php` — `'config' => 'encrypted:array'`
- `app/Http/Middleware/SecurityHeaders.php` — HSTS for production

### Findings
Only `DataConnector.config` is encrypted. Audit logs, AI usage records, intelligence insights, and analytics snapshots may contain PII but are stored in plaintext. No right-to-erasure flow exists.

### Required Actions
- [ ] Add `PiiScrubber` service that tokens PII before prompt assembly — guide: `docs/security/ai-governance.md`
- [ ] Implement `TeamDataExportService` and `TeamDataEraseService` — guide: `docs/compliance/gdpr-popia.md`
- [ ] Define key rotation policy in `docs/security/secrets.md`
- [ ] Add `analytics:prune-data` command for retention enforcement

---

## 6. API Security (Weight: 8%) — Score: 82/100

### Checklist
- [x] Versioned API (`/api/v1/`)
- [x] OpenAPI spec (`php artisan analytics:openapi`)
- [x] 3-tier rate limiting (120/300/10 per minute)
- [x] Input validation on all endpoints
- [x] Sanctum bearer token auth
- [x] HMAC webhook signature validation
- [x] SSRF protection in connectors
- [ ] Webhook replay protection (nonce/timestamp)
- [ ] API response field-level filtering (no over-exposure)

### Evidence
- `app/Http/Controllers/Api/V1/IngestController.php` — HMAC validation
- `app/Providers/AppServiceProvider.php` — 3 named rate limiters
- `public/api-docs/openapi.json` — generated spec

### Findings
Webhook replay attacks are possible — a captured valid HMAC payload can be replayed indefinitely. No timestamp or nonce validation.

### Required Actions
- [ ] Add `X-Analytics-Timestamp` + nonce validation in `IngestController` — guide: `docs/security/api-security.md`
- [ ] Cache HMAC signatures in Redis for 5-minute replay window

---

## 7. Application Security (Weight: 8%) — Score: 80/100

### Checklist
- [x] CSRF protection (Laravel default)
- [x] XSS — Blade auto-escaping + `e()` in reports
- [x] CSP header (`SecurityHeaders` middleware)
- [x] SQL injection prevention (Eloquent ORM)
- [x] SSRF prevention (`assertNotSsrf()`)
- [x] RCE prevention (`eval()` replaced with recursive descent parser)
- [x] Secure HTTP headers (X-Frame-Options, HSTS, etc.)
- [x] Dependency vulnerability scanning (`composer audit` in CI)
- [ ] Prompt injection defenses
- [ ] PHPStan `|| true` removed from CI

### Evidence
- `app/Services/Connectors/RestApiConnector.php` — `assertNotSsrf()`
- `app/Services/PipelineExecutionService.php` — `safeArithmetic()` replaces `eval()`
- `app/Http/Middleware/SecurityHeaders.php`
- `tests/Unit/Security/SsrfProtectionTest.php` — 8 SSRF tests

### Required Actions
- [ ] Remove `|| true` from PHPStan CI step — fix underlying type errors
- [ ] Add prompt injection guard in `AiModelRouter::complete()` — guide: `docs/security/ai-governance.md`
- [ ] Add secret scanning (gitleaks) to CI — guide: `docs/sdlc/code-quality.md`

---

## 8. Infrastructure & Cloud (Weight: 8%) — Score: 65/100

### Checklist
- [x] Multi-stage Dockerfile (vendor → assets → production)
- [x] docker-compose with 6 services
- [x] Nginx hardened config with security headers
- [x] PHP + OPcache production config
- [x] Secrets via environment variables only
- [ ] Non-root Docker user (`USER www-data` missing)
- [ ] Kubernetes manifests
- [ ] Container image scanning
- [ ] Network policies

### Evidence
- `Dockerfile` — multi-stage production build
- `docker-compose.yml` — app, queue, scheduler, reverb, postgres, redis
- `docker/nginx/default.conf`, `docker/php/php.ini`, `docker/php/opcache.ini`
- `docker/supervisor/supervisord.conf`

### Required Actions
- [ ] Add `USER www-data` to Dockerfile production stage — guide: `docs/infrastructure/docker-hardening.md`
- [ ] Add `aquasecurity/trivy-action` to CI build job
- [ ] Create `k8s/` folder with Deployment, Service, Ingress, HPA manifests — guide: `docs/infrastructure/kubernetes.md`

---

## 9. Secrets Management (Weight: 5%) — Score: 60/100

### Checklist
- [x] No secrets in source control (`.env` gitignored)
- [x] `DataConnector.config` encrypted at rest
- [x] AI API keys never appear in logs or responses
- [ ] External secret manager (Vault / AWS SSM)
- [ ] Key rotation policy documented
- [ ] Secret scanning in CI

### Evidence
- `.gitignore` — `.env` excluded
- `.env.example` — no real secrets
- `app/Models/DataConnector.php` — `encrypted:array`

### Required Actions
- [ ] Add gitleaks secret scanning to CI — guide: `docs/sdlc/code-quality.md`
- [ ] Document key rotation policy — guide: `docs/security/secrets.md`
- [ ] Add Vault/AWS SSM binding guide — guide: `docs/security/secrets.md`

---

## 10. Logging & Audit (Weight: 5%) — Score: 70/100

### Checklist
- [x] Immutable `audit_logs` (model throws on update/delete)
- [x] Platform connect/disconnect/update events logged (`DataSourceObserver`)
- [x] Structured logging service (`StructuredLogger`)
- [x] Security event logging (`StructuredLogger::security()`)
- [ ] Login / MFA / token events in `audit_logs`
- [ ] Audit log export endpoint
- [ ] Log retention policy
- [ ] Cryptographic hash chaining for tamper detection

### Evidence
- `app/Models/AuditLog.php` — immutability enforced
- `app/Observers/DataSourceObserver.php` — writes on every platform change
- `app/Services/StructuredLogger.php`
- `tests/Unit/Observers/DataSourceObserverTest.php` — tamper test passing

### Required Actions
- [ ] Register Fortify listeners to capture auth events in `audit_logs` — guide: `docs/security/audit-logging.md`
- [ ] Add `GET /api/v1/audit-logs` + CSV export — guide: `docs/security/audit-logging.md`
- [ ] Add `hash` column for SHA-256 chain + `/api/v1/audit-logs/verify-chain` — guide: `docs/security/audit-logging.md`
- [ ] Add `analytics:prune-logs` command with configurable retention — guide: `docs/security/audit-logging.md`

---

## 11. Monitoring & Detection (Weight: 5%) — Score: 58/100

### Checklist
- [x] Detailed health check (`GET /api/v1/health/detailed` — 6 subsystems)
- [x] Liveness probe (`GET /api/ping`)
- [x] Anomaly detection (`AnomalyDetectionService` Z-score + IQR)
- [x] AI usage metrics (`ai_model_usage` table)
- [x] Structured logs with correlation IDs
- [ ] Distributed tracing (OpenTelemetry)
- [ ] Horizon queue dashboard
- [ ] SIEM connector / webhook
- [ ] Incident response runbook

### Evidence
- `app/Http/Controllers/Api/V1/HealthController.php`
- `app/Services/AnomalyDetectionService.php`
- `app/Services/StructuredLogger.php`

### Required Actions
- [ ] Install `open-telemetry/opentelemetry-php` and configure OTLP exporter — guide: `docs/observability/monitoring.md`
- [ ] Install Laravel Horizon (`composer require laravel/horizon`) when `ext-pcntl` available — guide: `docs/observability/monitoring.md`
- [ ] Create incident response runbook — guide: `docs/observability/incident-response.md`
- [ ] Add AI cost budget alerting in `AiModelRouter` — guide: `docs/security/ai-governance.md`

---

## 12. Compliance & Privacy (Weight: 5%) — Score: 35/100

### Checklist
- [x] Multi-locale / multi-currency support (`SetLocale`, `CurrencyService`)
- [x] Immutable audit trail
- [ ] GDPR Article 30 records of processing
- [ ] GDPR right to erasure
- [ ] GDPR right to access / data portability
- [ ] POPIA compliance documentation
- [ ] ISO 27001 control mapping
- [ ] SOC 2 readiness assessment

### Required Actions
- [ ] Implement `TeamDataEraseService` — cascades anonymisation of PII on erasure request — guide: `docs/compliance/gdpr-popia.md`
- [ ] Implement data export endpoint (`GET /api/v1/my-data`) — guide: `docs/compliance/gdpr-popia.md`
- [ ] Create ISO 27001 control mapping table — guide: `docs/compliance/iso27001-mapping.md`
- [ ] Create Article 30 records of processing document — guide: `docs/compliance/gdpr-popia.md`

---

## 13. Secure SDLC (Weight: 5%) — Score: 68/100

### Checklist
- [x] CI pipeline (`.github/workflows/ci.yml`)
- [x] `composer audit` dependency scanning
- [x] `npm audit` (0 vulnerabilities)
- [x] PHPStan static analysis (level 5)
- [ ] Test coverage ≥95% (currently 77.5%)
- [ ] DAST configured
- [ ] Secret scanning (gitleaks)
- [ ] Penetration test conducted
- [ ] PHPStan `|| true` removed

### Evidence
- `.github/workflows/ci.yml` — 4 jobs: test, lint, security, docker-build
- `phpunit.xml` — in-memory SQLite for fast tests

### Required Actions
- [ ] Write ~60 targeted tests to reach 95% — guide: `docs/sdlc/test-coverage-guide.md`
- [ ] Remove `|| true` from PHPStan CI step — guide: `docs/sdlc/code-quality.md`
- [ ] Add gitleaks secret scanning action — guide: `docs/sdlc/code-quality.md`
- [ ] Schedule annual penetration test; document scope — guide: `docs/sdlc/code-quality.md`

---

## 14. AI Security & Governance (Weight: 5%) — Score: 62/100

### Checklist
- [x] Multi-model routing with allowlisted providers (`AiModelRouter`)
- [x] AI usage audit log (`ai_model_usage`)
- [x] Confidence scores on all outputs
- [x] SQL injection prevention (`assertSafe()` in `AiSqlService`)
- [ ] Prompt injection guard
- [ ] PII filtering before prompt submission
- [ ] Human approval workflow for high-impact recommendations
- [ ] AI cost budget alerts and hard caps
- [ ] Explainability — full evidence chain

### Evidence
- `app/Services/AiModelRouter.php` — provider allowlist, cost tracking
- `app/Services/AiSqlService.php` — SELECT-only enforcement
- `tests/Unit/Services/AiSqlServiceTest.php` — injection tests passing

### Required Actions
- [ ] Add `PiiScrubber` service before all prompt assembly — guide: `docs/security/ai-governance.md`
- [ ] Add prompt injection detection (pattern match on `IGNORE`, `SYSTEM`, `jailbreak`) — guide: `docs/security/ai-governance.md`
- [ ] Add `daily_ai_budget_usd` to `teams` table + hard cap enforcement in `AiModelRouter`
- [ ] Add `requires_approval` flag on critical recommendations — guide: `docs/security/ai-governance.md`

---

## 15. Business Continuity (Weight: 4%) — Score: 30/100

### Checklist
- [x] Supervisord manages all processes (app, queue, scheduler, reverb)
- [x] 2× queue workers configured
- [ ] PostgreSQL HA (Patroni / RDS Multi-AZ)
- [ ] Redis HA (Sentinel / Cluster)
- [ ] RPO / RTO defined
- [ ] Disaster recovery runbook
- [ ] Backup policy and tested restore
- [ ] Multi-region deployment
- [ ] Chaos testing conducted

### Required Actions
- [ ] Define RPO/RTO targets — guide: `docs/infrastructure/disaster-recovery.md`
- [ ] Create DR runbook — guide: `docs/infrastructure/disaster-recovery.md`
- [ ] Add PostgreSQL replica to `docker-compose.yml`
- [ ] Add Redis Sentinel to `docker-compose.yml`
- [ ] Schedule quarterly DR drill

---

## 16. Vendor Readiness Evidence (Weight: 3%) — Score: 40/100

| Artifact | Status | Location |
|---|---|---|
| OpenAPI specification | ✅ Generated | `public/api-docs/openapi.json` — `php artisan analytics:openapi` |
| Test coverage report | ⚠️ 77.5% | `XDEBUG_MODE=coverage php artisan test --coverage` |
| Security scorecard | ✅ Created | `docs/enterprise-security-scorecard.md` |
| Architecture diagrams (C4) | ❌ Not created | Gap |
| Threat model | ❌ Not created | Gap |
| Penetration test report | ❌ Not conducted | Gap |
| Security policy document | ❌ Not created | Gap |
| Incident response plan | ❌ Not created | `docs/observability/incident-response.md` |
| DR documentation | ❌ Not created | `docs/infrastructure/disaster-recovery.md` |
| ISO 27001 / SOC 2 mapping | ❌ Not created | `docs/compliance/iso27001-mapping.md` |
| SBOM | ❌ Not generated | `composer show --format=json > sbom.json` |

### Required Actions
- [ ] Generate SBOM: `composer show --format=json > docs/sbom.json`
- [ ] Create threat model using STRIDE methodology — guide: `docs/security/threat-model.md`
- [ ] Schedule penetration test (minimum annual)

---

## Scoring Guide

| Range | Rating |
|---|---|
| 95–100 | Enterprise Vendor Ready |
| 90–94 | Production Ready (Minor Gaps) |
| 80–89 | Strong — Improvements Required |
| 70–79 | Moderate Risk |
| 60–69 | Significant Gaps |
| < 60 | Not Enterprise Ready |

---

## Final Sign-off

| Domain | Result |
|---|---|
| Architecture | PASS |
| Security | CONDITIONAL — close Tier 1 gaps first |
| Compliance | FAIL — GDPR/POPIA gaps must be addressed |
| Operations | CONDITIONAL — DR plan required |
| Vendor Readiness | FAIL — artifacts missing |

**Current Score: 63.9/100 — Moderate Risk**
**Target Score: 90+ for Enterprise Vendor Ready**

**Priority order to reach 90:**
1. Global Eloquent scopes (Multi-tenancy: +12 points)
2. Missing model policies (Authorization: +8 points)
3. GDPR right to erasure/access (Compliance: +20 points)
4. Login events in audit_logs (Logging: +8 points)
5. Webhook replay protection (API Security: +5 points)
6. Password policy hardening (IAM: +6 points)
7. Non-root Docker user (Infrastructure: +5 points)
8. Secret scanning in CI (SDLC: +4 points)

**Signed by:** ___________________________ **Date:** ___________________

**Next Assessment Due:** ___________________________
