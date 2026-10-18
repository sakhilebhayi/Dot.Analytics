<details data-testid="metrics-contract" @if($open) open @endif style="border:1px solid var(--line);background:var(--panel);">
    <summary class="font-mono" style="cursor:pointer;padding:0.6rem 0.9rem;font-size:0.64rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--mist);">What a platform sends</summary>
    <div style="padding:0.25rem 0.9rem 0.9rem;">
        <p style="font-size:0.75rem;color:var(--mist);margin:0 0 0.6rem;max-width:70ch;line-height:1.55;">
            One signed snapshot per day, of the platform's own headline numbers. Keys outside the catalogue are dropped; a value of the wrong kind is rejected. Money is whole cents plus an ISO currency. Sending the same snapshot twice is safe. The full contract and catalogue are in <code class="font-mono">docs/metrics-contract.md</code>.
        </p>
        <pre class="font-mono" style="margin:0;padding:0.75rem 0.9rem;background:var(--ink);border:1px solid var(--line);font-size:0.68rem;line-height:1.6;color:var(--paper);overflow-x:auto;">POST /api/v1/ingest/{platform}
Authorization: Bearer &lt;token of a member of the company's team&gt;
X-Analytics-Signature: sha256=&lt;HMAC-SHA256 of the raw body, keyed with the platform's webhook secret&gt;

{
  "company_uuid": "&lt;InfoDot company uuid&gt;",
  "platform": "&lt;platform key, same as the URL&gt;",
  "taken_at": "&lt;ISO 8601 with a zone, e.g. 2026-10-17T06:00:00Z&gt;",
  "period": "day",
  "metrics": { "&lt;catalogue key&gt;": &lt;number&gt;, "currency": "&lt;ISO 4217, only with *_cents keys&gt;" }
}</pre>
        <p class="font-mono" style="font-size:0.62rem;color:var(--mist);margin:0.6rem 0 0;line-height:1.6;">
            Platforms: {{ implode(', ', array_map(fn ($p) => $p, (new \App\Services\Metrics\MetricCatalogue)->platformKeys())) }}
        </p>
    </div>
</details>
