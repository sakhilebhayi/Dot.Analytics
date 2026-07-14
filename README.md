<div align="center">

<h1>Dot.Analytics</h1>

<p>The intelligence layer of the Dot ecosystem — not a BI tool, not a dashboard platform, but the <strong>central nervous system</strong> that continuously consumes data from every Dot platform, understands the relationships between them, and produces intelligence for the entire ecosystem.</p>

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-3.x-4E56A6?style=flat-square)](https://livewire.laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql&logoColor=white)](https://postgresql.org)
[![AI](https://img.shields.io/badge/AI-Claude%20Sonnet-blueviolet?style=flat-square)](https://anthropic.com)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)

</div>

---

## What it is

Traditional BI tools (Power BI, Tableau, Looker, Zoho Analytics) are excellent at visualising data that users prepare for them. Dot.Analytics does something fundamentally different — it **understands relationships across the entire Dot ecosystem**, continuously generates insights, predicts outcomes, recommends actions, and feeds intelligence back into every platform.

Every Dot platform **contributes knowledge to** Dot.Analytics. Every Dot platform **benefits from** it. As organisations adopt more Dot products, the intelligence becomes richer, creating a network effect that is extremely difficult to replicate externally.

---

## Ecosystem Architecture

```
                    Dot.Analytics
                  (Intelligence Core)

    Dot.Fleet    Dot.CRM      Dot.HR       Dot.Documents
    Dot.Hear     Dot.Support  Dot.Inventory Dot.Payments
    Dot.Security Dot.API      Dot.Flow      Dot.Assets
    Dot.Agents   Dot.Finance  Dot.Vault

              + Third-party systems
```

Every platform contributes intelligence. Every platform receives intelligence. No platform is "above" the others — Dot.Analytics is the layer that connects them all.

---

## The 17 Intelligence Engines

Each engine consumes data from multiple Dot platforms and produces a holistic view rather than isolated reports.

| Engine | Primary Sources |
|---|---|
| Data Intelligence | Dot.API, Dot.Security, Dot.Vault |
| Business Intelligence | Dot.CRM, Dot.HR, Dot.Payments, Dot.Finance |
| Operational Intelligence | Dot.Fleet, Dot.Inventory, Dot.Support, Dot.Flow |
| Financial Intelligence | Dot.Payments, Dot.Finance, Dot.Fleet, Dot.Assets |
| People Intelligence | Dot.HR, Dot.Agents, Dot.Support |
| Customer Intelligence | Dot.CRM, Dot.Support, Dot.Payments, Dot.Hear |
| Document Intelligence | Dot.Documents |
| Community Intelligence | Dot.Hear |
| AI Intelligence | Dot.Agents |
| Predictive Intelligence | Dot.Fleet, Dot.CRM, Dot.HR, Dot.Inventory, Dot.Payments |
| Decision Intelligence | Dot.CRM, Dot.Finance, Dot.Agents, Dot.Flow |
| Risk Intelligence | Dot.CRM, Dot.Payments, Dot.HR, Dot.Security, Dot.Documents |
| Security Intelligence | Dot.Security, Dot.Vault, Dot.API |
| Asset Intelligence | Dot.Fleet, Dot.Assets |
| Mining Intelligence | Dot.Fleet, Dot.HR, Dot.Assets, Dot.Inventory |
| Agriculture Intelligence | Dot.Fleet, Dot.Assets, Dot.Inventory, Dot.HR |
| Construction Intelligence | Dot.Fleet, Dot.HR, Dot.Assets, Dot.Documents |

---

## Cross-Platform Intelligence (The Real Superpower)

The defining capability of Dot.Analytics is insight that **no single platform can produce alone**.

**Example — Mining operation:**
> Productivity dropped 8% because three certified operators were on leave (Dot.HR), resulting in elevated equipment idle time (Dot.Fleet), increased overtime costs (Dot.Finance), and delayed maintenance schedules (Dot.Assets). This also caused a support ticket spike (Dot.Support) and reduced production targets.

No individual platform sees this. Only Dot.Analytics does.

**Example — At-risk customer:**
> Customer sentiment decreased (Dot.Hear). Sales to that customer dropped (Dot.CRM). Support tickets increased (Dot.Support). Invoices became overdue (Dot.Payments). AI agents contacted them twice without response (Dot.Agents). Contract expires next month (Dot.Documents).

Result: *"High-risk customer — immediate intervention required."*

---

## Business DNA Profile

Every organisation on the Dot ecosystem has a unique operational fingerprint. Dot.Analytics continuously builds a **Business DNA Model** by learning:

- How the company operates day-to-day
- Seasonal trends and demand cycles
- Decision-making patterns
- Team performance profiles
- Customer behaviour segments
- Financial cycles and cash flow patterns
- Operational bottlenecks
- Risk tolerance
- Growth opportunities

This evolving model becomes increasingly accurate over time, allowing intelligence engines to produce recommendations tailored to that specific organisation rather than relying on generic analytics.

---

## Universal Intelligence Graph

Rather than isolated databases, Dot.Analytics maintains an internal knowledge graph connecting every entity across the ecosystem:

```
Customer → Orders → Invoices → Payments → Support Tickets
       → Equipment → Operators → Maintenance → Fuel
       → Community Posts → AI Conversations → Contracts
       → Projects → Inventory → Suppliers
```

This graph enables AI to answer questions no traditional BI system can answer — tracing causal chains across the entire ecosystem.

---

## Features

- **Ecosystem Map** — visual catalog of all 15 Dot platforms, click-to-connect, active engine coverage
- **Universal Intelligence Query** — ask any natural-language question, answered across all platforms
- **Cross-Platform Insights** — automatically discovered multi-platform correlations and causal chains
- **Business DNA Panel** — evolving organisational fingerprint with confidence scoring
- **17 Intelligence Engines** — each consuming multiple platforms for holistic intelligence
- **55+ Metric Definitions** — pre-seeded across every platform and engine
- **Intelligence Graph** — `IntelligenceNode` / `IntelligenceEdge` model for entity relationship mapping
- **Predictive Alerts** — cross-platform signals surfaced as severity-ranked alerts
- **AI Recommendations** — actionable insights with cross-platform rationale
- **Ecosystem SSO** — authenticate from InfoDot with a single click

---

## Domain Model

```
Team
 ├── DataSource (connected Dot platforms)
 ├── AnalyticsSnapshot (raw ingested platform data)
 ├── CrossPlatformInsight (multi-platform discoveries)
 ├── IntelligenceEngineRun (engine execution audit)
 ├── IntelligenceNode / IntelligenceEdge (knowledge graph)
 ├── BusinessDnaProfile (evolving organisational fingerprint)
 ├── MetricDefinition → ComputedMetric (55+ metrics)
 ├── AnalyticsAlert (cross-platform signals)
 ├── Recommendation (AI-generated actions)
 └── AnalyticsDashboard → DashboardWidget
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 + PHP 8.4 |
| Frontend | Livewire 3 + Alpine.js + Tailwind CSS |
| Auth | Jetstream 5 + Sanctum (ecosystem SSO) |
| Database | PostgreSQL 16 (shared infodot instance) |
| AI | Anthropic Claude Sonnet (mock fallback when key absent) |

---

## Quick Start

```bash
git clone https://github.com/sakhileb/Dot.Analytics.git && cd Dot.Analytics
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run dev & php artisan serve
```

Set `ANTHROPIC_API_KEY` in `.env` to enable live AI intelligence. Without it, all engines fall back to deterministic rule-based responses automatically.

---

## Part of the Dot Ecosystem

Dot.Analytics connects to [InfoDot](https://github.com/sakhileb/InfoDot) — the central hub. Log in to InfoDot once and navigate here without re-authenticating via `/auth/ecosystem`.

---

MIT — © SK Digital / BluPin Incorporated
