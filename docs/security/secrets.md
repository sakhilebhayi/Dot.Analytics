# Secrets Management — Guide

**Scorecard domain:** Secrets Management (currently 60/100)
**Target:** 85/100

---

## Current State

- Secrets in `.env`, excluded from git ✅
- `DataConnector.config` uses `encrypted:array` ✅
- AI API keys never logged ✅
- No external secret manager ❌
- No key rotation policy ❌
- No secret scanning in CI ❌

---

## Fix 1 — Secret Scanning in CI (Quick Win, 30 minutes)

Add to `.github/workflows/ci.yml` under the `security` job:

```yaml
- name: Scan for secrets (gitleaks)
  uses: gitleaks/gitleaks-action@v2
  env:
    GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
  with:
    config-path: .gitleaks.toml
```

Create `.gitleaks.toml` in the repo root:

```toml
title = "Dot.Analytics Gitleaks Config"

[[rules]]
id = "anthropic-key"
description = "Anthropic API Key"
regex = '''sk-ant-[a-zA-Z0-9-]{32,}'''
tags = ["ai", "anthropic"]

[[rules]]
id = "openai-key"
description = "OpenAI API Key"
regex = '''sk-[a-zA-Z0-9]{48}'''
tags = ["ai", "openai"]

[[rules]]
id = "google-ai-key"
description = "Google AI Key"
regex = '''AIza[0-9A-Za-z-_]{35}'''
tags = ["ai", "google"]

[allowlist]
  description = "Allowlist for known safe patterns"
  regexes = [
    '''ANTHROPIC_API_KEY=\'\'\'',        # Empty key in .env.example
    '''OPENAI_API_KEY=\'\'\'',
  ]
```

---

## Fix 2 — Key Rotation Policy

Rotate the following on this schedule:

| Secret | Rotation Frequency | Who owns it | How to rotate |
|---|---|---|---|
| `APP_KEY` | On compromise + annual | DevOps | `php artisan key:generate` → redeploy |
| `ANTHROPIC_API_KEY` | Quarterly | Platform team | Regenerate on Anthropic console |
| `OPENAI_API_KEY` | Quarterly | Platform team | Regenerate on OpenAI console |
| `REVERB_APP_SECRET` | On compromise | DevOps | Update env + restart Reverb |
| `DB_PASSWORD` | Bi-annually | DBA | Blue-green: create new user → update env → delete old |
| `REDIS_PASSWORD` | Bi-annually | DevOps | Rolling restart with new password |
| Webhook HMAC secrets | Per tenant, on request | Tenant admin | Re-issue via API |

---

## Fix 3 — AWS SSM Parameter Store Integration

For production deployments, replace `.env` secrets with SSM:

```bash
composer require aws/aws-sdk-php
```

Create `app/Console/Commands/LoadSecretsCommand.php`:

```php
protected $signature = 'secrets:load {--env=production}';

public function handle(): int
{
    $ssm    = new \Aws\Ssm\SsmClient(['region' => env('AWS_DEFAULT_REGION', 'af-south-1')]);
    $prefix = '/dot-analytics/' . $this->option('env') . '/';

    $params = $ssm->getParametersByPath([
        'Path'           => $prefix,
        'WithDecryption' => true,
        'Recursive'      => true,
    ])->get('Parameters');

    foreach ($params as $param) {
        $key = strtoupper(str_replace([$prefix, '/'], ['', '_'], $param['Name']));
        putenv("{$key}={$param['Value']}");
        $_ENV[$key] = $param['Value'];
    }

    $this->info('Loaded ' . count($params) . ' secrets from SSM.');
    return self::SUCCESS;
}
```

Call in `bootstrap/app.php` before configuration is cached:

```php
// In production only:
if (app()->environment('production') && ! app()->configurationIsCached()) {
    Artisan::call('secrets:load');
}
```

---

## Fix 4 — HashiCorp Vault Integration (Enterprise)

For enterprise deployments with HashiCorp Vault:

```bash
composer require mittwald/vault-php
```

Add a Vault binding in `AppServiceProvider`:

```php
$this->app->bind('vault', function () {
    return new \Vault\Client(
        new \Vault\AuthTokenHandler(env('VAULT_TOKEN')),
        new \GuzzleHttp\Client(['base_uri' => env('VAULT_ADDR', 'https://vault.infodot.app')])
    );
});
```

---

## Fix 5 — `.env.example` Audit

Every entry in `.env.example` must be documented:

```bash
# AI Providers — set at least one for live intelligence
ANTHROPIC_API_KEY=""          # Required for Claude models
OPENAI_API_KEY=""             # Optional fallback
GOOGLE_AI_KEY=""              # Optional fallback (Gemini)
DEEPSEEK_API_KEY=""           # Optional fallback
AI_PRIMARY_PROVIDER=anthropic # Which provider to prefer

# Reverb WebSocket — required for real-time dashboard updates
REVERB_APP_ID=""
REVERB_APP_KEY=""
REVERB_APP_SECRET=""          # Never commit a real value
```

---

## Checklist

- [ ] Gitleaks added to CI `security` job
- [ ] `.gitleaks.toml` config file created
- [ ] Key rotation schedule documented and calendar reminders set
- [ ] `.env.example` fully documented with inline comments
- [ ] AWS SSM or Vault integration guide reviewed and prioritised
- [ ] PHPStan `|| true` removed so secrets-related type errors surface
- [ ] Scorecard updated
