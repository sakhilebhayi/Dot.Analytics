# Code Quality & Secure SDLC — Guide

**Scorecard domain:** Secure SDLC (currently 68/100)
**Target:** 88/100

---

## Fix 1 — Remove PHPStan `|| true` Escape Hatch (Quick Win)

The CI lint job currently silently swallows static analysis failures:

```yaml
# Current (broken):
- name: Run PHPStan
  run: vendor/bin/phpstan analyse app --level=5 --no-progress || true
```

Fix:

```yaml
# Correct:
- name: Run PHPStan
  run: vendor/bin/phpstan analyse app --level=5 --no-progress
```

To identify what's currently failing:

```bash
vendor/bin/phpstan analyse app --level=5 --no-progress 2>&1 | head -50
```

Common fixes needed:

```php
// Missing return type:
public function handle(): void { ... }

// Nullable not handled:
$user = Auth::user();
if (! $user) { return; }  // Add null guard

// Mixed array type:
/** @return array<int, array{title: string, confidence: float}> */
private function buildInsights(): array { ... }
```

Incrementally increase the level: 5 → 6 → 7 as issues are resolved.

---

## Fix 2 — Secret Scanning (Quick Win, 30 minutes)

Add to `.github/workflows/ci.yml` `security` job:

```yaml
security:
  name: Security Audit
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
      with:
        fetch-depth: 0  # Full history for gitleaks

    - name: Secret scanning (gitleaks)
      uses: gitleaks/gitleaks-action@v2
      env:
        GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

    - name: PHP dependency audit
      run: |
        composer install --no-interaction
        composer audit

    - name: npm dependency audit
      run: |
        npm ci
        npm audit --audit-level=high
```

---

## Fix 3 — DAST with OWASP ZAP (Quarterly)

Add a scheduled workflow `.github/workflows/dast.yml`:

```yaml
name: DAST Scan

on:
  schedule:
    - cron: '0 2 * * 0'  # Weekly on Sunday at 2am
  workflow_dispatch:

jobs:
  zap:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Start application
        run: docker compose up -d --wait

      - name: ZAP API Scan
        uses: zaproxy/action-api-scan@v0.7.0
        with:
          target: 'http://localhost:8000/api-docs/openapi.json'
          format: openapi
          fail_action: true
          cmd_options: '-a'

      - name: Upload ZAP report
        uses: actions/upload-artifact@v4
        with:
          name: zap-report
          path: report_html.html
```

---

## Fix 4 — Enforce Conventional Commits

Add `.github/workflows/pr-lint.yml`:

```yaml
name: PR Lint
on:
  pull_request:
    types: [opened, edited, synchronize]

jobs:
  commitlint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - uses: wagoid/commitlint-github-action@v5
```

Create `.commitlintrc.json`:

```json
{
  "extends": ["@commitlint/config-conventional"],
  "rules": {
    "type-enum": [2, "always", [
      "feat", "fix", "security", "perf", "refactor",
      "test", "docs", "chore", "ci", "revert"
    ]],
    "subject-max-length": [2, "always", 100]
  }
}
```

The `security` type makes security fixes visible in changelogs.

---

## Fix 5 — Pre-commit Hooks

Create `.pre-commit-config.yaml`:

```yaml
repos:
  - repo: https://github.com/gitleaks/gitleaks
    rev: v8.18.1
    hooks:
      - id: gitleaks

  - repo: local
    hooks:
      - id: phpstan
        name: PHPStan
        entry: vendor/bin/phpstan analyse
        language: system
        files: \.php$
        pass_filenames: false
        args: ['--level=5', '--no-progress']

      - id: php-lint
        name: PHP Syntax
        entry: php -l
        language: system
        files: \.php$
```

Install:

```bash
pip install pre-commit
pre-commit install
```

---

## PR Security Checklist

Add `.github/pull_request_template.md`:

```markdown
## Changes

## Security Review

- [ ] No new queries without team scoping
- [ ] No new `auth()->user()` (use `Auth::user()` facade)
- [ ] New models have policies
- [ ] No `eval()`, `exec()`, `shell_exec()`, `system()`
- [ ] No secrets committed
- [ ] Rate limiting applied to new routes
- [ ] Input validation added to new endpoints
- [ ] Tests written for security paths
- [ ] PHPStan passes (`vendor/bin/phpstan analyse app --level=5`)

## Test Coverage

Before: ____%  After: ____%
```

---

## Checklist

- [ ] PHPStan `|| true` removed from CI
- [ ] PHPStan type errors fixed (run locally first)
- [ ] Gitleaks secret scanning added to CI
- [ ] `.gitleaks.toml` created
- [ ] DAST workflow created (scheduled weekly)
- [ ] Conventional commits enforced on PRs
- [ ] Pre-commit hooks configured
- [ ] PR security checklist template added
- [ ] Scorecard updated
