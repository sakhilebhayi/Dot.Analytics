# Data Visualization Standards

**Purpose:** Define how numbers, trends, metrics, and relationships are
displayed across the dashboard. Consistency builds trust in an intelligence platform.

---

## Number Formatting

Use `CurrencyService` and `IntelligenceEngineService` for context-aware formatting.

### Metric Values

| Magnitude | Format | Example |
|---|---|---|
| < 1,000 | Full number | `847` |
| 1,000 – 9,999 | Comma-separated | `4,230` |
| 10,000+ | Compact (K) | `42.3K` |
| 1,000,000+ | Compact (M) | `1.4M` |
| Percentage | 1 decimal | `83.5%` |
| Currency | Symbol + comma | `R 42,300` (use `CurrencyService`) |
| Confidence | Round to 0 decimal | `87%` (not `87.34%`) |
| Duration | Human-readable | `3h 14m` or `2 days` |

### Blade helper usage

```html
{{-- For currency metrics --}}
{{ app(\App\Services\CurrencyService::class)->format($value, Auth::user()->currentTeam->currency, compact: $value > 10000) }}

{{-- For large counts --}}
@php
    $formatted = $value >= 1_000_000 ? round($value/1_000_000, 1).'M'
               : ($value >= 1_000   ? round($value/1_000, 1).'K'
               : $value);
@endphp
{{ $formatted }}
```

---

## KPI Cards

KPI cards are the first thing users see on the dashboard. They must communicate:
- **The number** (large, dominant)
- **The label** (small, descriptive)
- **The context** (denominator, unit, period)
- **The trend** (optional, directional)

```html
<!-- Standard KPI card -->
<div class="bg-white rounded-xl shadow p-5 border-l-4 {{ $borderColor }}">
    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
        {{ $label }}
    </div>
    <div class="text-3xl font-bold {{ $valueColor }}">
        {{ $formattedValue }}
    </div>
    <div class="text-xs text-gray-400 mt-0.5">{{ $context }}</div>

    @if(isset($trend))
        <div class="text-xs mt-1 {{ $trend > 0 ? 'text-green-600' : 'text-red-500' }}">
            {{ $trend > 0 ? '↑' : '↓' }} {{ abs($trend) }}% vs last period
        </div>
    @endif
</div>
```

### KPI card border colors (current, keep consistent)

```
Connected Platforms → border-blue-500
Active Engines      → border-indigo-500
Open Alerts         → border-red-400
Pending Actions     → border-amber-400
```

---

## Progress / Confidence Bars

For scores, confidence values, coverage percentages:

```html
@php
    $pct   = round($value * 100);
    $color = $pct >= 80 ? 'bg-green-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-gray-400');
@endphp
<div class="flex items-center justify-between mb-1">
    <span class="text-xs text-gray-500">{{ $label }}</span>
    <span class="text-xs font-semibold {{ str_replace('bg-', 'text-', $color) }}">{{ $pct }}%</span>
</div>
<div class="w-full bg-gray-100 rounded-full h-1.5">
    <div class="{{ $color }} h-1.5 rounded-full transition-all duration-500"
         style="width: {{ $pct }}%"
         role="progressbar"
         aria-valuenow="{{ $pct }}"
         aria-valuemin="0"
         aria-valuemax="100"
         aria-label="{{ $label }}: {{ $pct }}%">
    </div>
</div>
```

---

## Trend Direction Convention

For every metric, define whether up is good or bad **in context**. Never assume.

| Metric | Up is... | Display |
|---|---|---|
| Revenue, connections, efficiency | Good | `text-green-600 ↑` |
| Costs, errors, alert count, risk score | Bad | `text-red-500 ↑` |
| Response time, idle rate | Bad | `text-red-500 ↑` |
| Confidence scores, coverage | Good | `text-green-600 ↑` |
| Anomaly count | Context-dependent | Show as neutral, let severity decide |

```html
@php
    $isPositive = in_array($metric->key, ['fleet.utilization_rate', 'crm.win_rate', ...]);
    $trendGood = $isPositive ? $trend > 0 : $trend < 0;
@endphp
<span class="{{ $trendGood ? 'text-green-600' : 'text-red-500' }}">
    {{ $trend > 0 ? '↑' : '↓' }} {{ abs($trend) }}%
</span>
```

---

## Platform Coverage Display

Intelligence engine coverage (how many sources are connected) should always show:
- Fraction: `3 of 5 sources`
- Percentage bar (see above)
- Color: green ≥75%, amber ≥40%, gray <40%

```html
<div class="text-right">
    <span class="text-xs font-semibold
        {{ $engine['coverage'] >= 75 ? 'text-green-600' :
           ($engine['coverage'] >= 40 ? 'text-amber-600' : 'text-gray-400') }}">
        {{ $engine['coverage'] }}%
    </span>
    <p class="text-xs text-gray-400">{{ count($engine['connected_sources']) }} of {{ count($engine['sources']) }} sources</p>
</div>
```

---

## Insight Confidence Distribution (future panel)

When the platform has enough data, show insight quality distribution:

```html
<!-- Confidence breakdown mini-chart (pure CSS) -->
@php
    $buckets = [
        'High (80–100%)' => $insights->where('confidence', '>=', 0.8)->count(),
        'Mid (60–79%)'   => $insights->whereBetween('confidence', [0.6, 0.799])->count(),
        'Low (<60%)'     => $insights->where('confidence', '<', 0.6)->count(),
    ];
    $total = $insights->count() ?: 1;
@endphp
@foreach($buckets as $label => $count)
    <div class="flex items-center gap-2 mb-1">
        <div class="text-xs text-gray-500 w-24 shrink-0">{{ $label }}</div>
        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-2 bg-indigo-400 rounded-full"
                 style="width: {{ round($count / $total * 100) }}%"></div>
        </div>
        <span class="text-xs text-gray-400 w-6 text-right">{{ $count }}</span>
    </div>
@endforeach
```

---

## Time-Based Data Display

For timestamps and relative time:

```html
{{-- Always show relative time with absolute as tooltip --}}
<time datetime="{{ $record->created_at->toIso8601String() }}"
      title="{{ $record->created_at->format('D, d M Y H:i T') }}">
    {{ $record->created_at->diffForHumans() }}
</time>
```

For period labels on metrics:

```html
@php
    $periodLabel = match($metric->period) {
        'daily'   => $metric->period_date->format('d M'),
        'weekly'  => 'W' . $metric->period_date->weekOfYear . ' ' . $metric->period_date->year,
        'monthly' => $metric->period_date->format('M Y'),
        default   => $metric->period_date->toDateString(),
    };
@endphp
<span class="text-xs text-gray-400">{{ $periodLabel }}</span>
```

---

## Chart Standards (Future)

When adding charts (e.g., Chart.js or ApexCharts):

| Chart type | When to use | When NOT to use |
|---|---|---|
| Line | Time series trends | Fewer than 3 data points |
| Bar | Comparison between categories | More than 10 categories |
| Horizontal bar | Ranking / leaderboard | Time series |
| Donut/Pie | Part-to-whole (max 5 segments) | Precise comparison needed |
| Sparkline | KPI card trend indicator | Standalone analysis |
| Heatmap | Correlation matrix | General data |

Chart color palette — use semantic colors, never rainbow:
```
Primary metric  → indigo-500
Comparison      → gray-300
Critical        → red-400
Warning         → amber-400
Positive        → green-400
```

---

## PR Checklist (Data Viz)

- [ ] Large numbers use compact format (K/M)
- [ ] Currency values use `CurrencyService`
- [ ] Progress bars have `role="progressbar"` with `aria-valuenow/min/max`
- [ ] Trend arrows pair with colour (not colour alone)
- [ ] Timestamps show relative time with absolute in `title` attribute
- [ ] Confidence scores rounded to 0 decimal places
- [ ] Platform coverage shows both fraction and percentage
