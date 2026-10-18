<?php

namespace App\Services;

use App\Models\Team;

/**
 * Registry of the REAL Dot platforms that report metrics to Dot.Analytics, and of
 * the intelligence engines that read them.
 *
 * Platform keys are the keys of config/ecosystem.php (tasks, auction, billing...)
 * and must stay in step with the metric catalogue in config/metrics.php: a
 * platform is connectable here only if it has a catalogue to report against.
 * Roster names that never existed (Dot.Fleet, Dot.CRM...) were removed.
 *
 * Every Dot platform is defined here with:
 *  - What data it contributes to the intelligence layer
 *  - Which intelligence engines it powers
 *  - The key metrics it enables
 *
 * Every intelligence engine is defined with:
 *  - Which platforms it consumes
 *  - What intelligence it produces
 */
class IntelligenceEngineService
{
    /**
     * The Dot platforms that can feed the intelligence layer (those with a metric catalogue).
     *
     * Format:
     *   'platform_key' => [
     *     'label'         => Human-readable name
     *     'description'   => One-line purpose
     *     'color'         => Tailwind colour name for UI
     *     'contributions' => Array of data points this platform contributes
     *     'engines'       => Array of engine keys this platform powers
     *     'produces'      => Array of intelligence outputs this platform enables
     *   ]
     */
    public const PLATFORMS = [
        'tasks' => [
            'label' => 'Dot.Tasks',
            'description' => 'Tasks, deadlines & team workload',
            'color' => 'amber',
            'contributions' => ['Open tasks', 'Overdue tasks', 'Tasks completed, 7 days', 'Tasks created, 7 days'],
            'engines' => ['operational', 'people', 'predictive', 'decision', 'prescriptive'],
            'produces' => ['Overdue-task trend', 'Completion throughput', 'Backlog growth'],
        ],

        'auction' => [
            'label' => 'Dot.Auction',
            'description' => 'Online auctions, bids & sales',
            'color' => 'orange',
            'contributions' => ['Active auctions', 'Auctions ended, 7 days', 'Gross sales, 7 days', 'Bids placed, 7 days'],
            'engines' => ['business', 'financial', 'predictive', 'retail'],
            'produces' => ['Gross sales trend', 'Bidding activity trend'],
        ],

        'ehail' => [
            'label' => 'Dot.Ehail',
            'description' => 'Ride hailing, drivers & trips',
            'color' => 'yellow',
            'contributions' => ['Rides requested, 7 days', 'Rides completed, 7 days', 'Rides expired unanswered, 7 days', 'Drivers online', 'Average driver rating'],
            'engines' => ['operational', 'customer', 'predictive'],
            'produces' => ['Ride fulfilment trend', 'Unanswered-request trend', 'Driver supply trend'],
        ],

        'hr' => [
            'label' => 'Dot.HR',
            'description' => 'People, leave & workforce records',
            'color' => 'purple',
            'contributions' => ['Headcount', 'On leave today', 'Pending leave requests'],
            'engines' => ['business', 'operational', 'people', 'risk', 'prescriptive'],
            'produces' => ['Headcount trend', 'Leave-approval backlog trend'],
        ],

        'farms' => [
            'label' => 'Dot.Farms',
            'description' => 'Crop cycles, harvests & farm operations',
            'color' => 'green',
            'contributions' => ['Active crop cycles', 'Harvests, 30 days', 'Harvested, 30 days (kg)'],
            'engines' => ['operational', 'predictive', 'agriculture'],
            'produces' => ['Harvest volume trend', 'Active-cycle trend'],
        ],

        'billing' => [
            'label' => 'Dot.Billing',
            'description' => 'Invoices, subscriptions & receivables',
            'color' => 'emerald',
            'contributions' => ['Monthly recurring revenue', 'Open invoices', 'Overdue invoices', 'Overdue amount'],
            'engines' => ['business', 'financial', 'customer', 'predictive', 'decision', 'risk', 'prescriptive'],
            'produces' => ['Recurring revenue trend', 'Overdue receivables trend'],
        ],

        'emall' => [
            'label' => 'Dot.Emall',
            'description' => 'Online store, orders & stock',
            'color' => 'teal',
            'contributions' => ['Orders, 7 days', 'Revenue, 7 days', 'Low-stock products'],
            'engines' => ['business', 'financial', 'customer', 'predictive', 'decision', 'retail', 'prescriptive'],
            'produces' => ['Order volume trend', 'Revenue trend', 'Low-stock trend'],
        ],

        'tutor' => [
            'label' => 'Dot.Tutor',
            'description' => 'Tutoring sessions & tutors',
            'color' => 'indigo',
            'contributions' => ['Sessions booked, 7 days', 'Sessions completed, 7 days', 'Active tutors'],
            'engines' => ['operational', 'people'],
            'produces' => ['Session completion trend', 'Tutor availability trend'],
        ],

        'pulse' => [
            'label' => 'Dot.Pulse',
            'description' => 'Community posts & engagement',
            'color' => 'pink',
            'contributions' => ['Posts, 7 days', 'Active users, 7 days', 'Posts held for review'],
            'engines' => ['customer', 'community', 'risk'],
            'produces' => ['Engagement trend', 'Moderation backlog trend'],
        ],

        'notify' => [
            'label' => 'Dot.Notify',
            'description' => 'Message delivery & suppression',
            'color' => 'cyan',
            'contributions' => ['Messages sent, 7 days', 'Messages failed, 7 days', 'Messages suppressed, 7 days'],
            'engines' => ['data', 'operational', 'risk'],
            'produces' => ['Delivery-failure trend'],
        ],

        'design' => [
            'label' => 'Dot.Design',
            'description' => 'Design projects & assets',
            'color' => 'violet',
            'contributions' => ['Design projects', 'Design assets'],
            'engines' => ['operational'],
            'produces' => ['Project and asset volume trend'],
        ],
    ];

    /**
     * The intelligence engines and the REAL Dot platforms (PLATFORMS keys) each
     * one consumes. An engine with no source has no platform that can feed it yet:
     * it is dormant (never active, never run) rather than faked.
     *
     * Format:
     *   'engine_key' => [
     *     'label'    => Human-readable name
     *     'sources'  => Platform keys this engine consumes
     *     'produces' => Intelligence outputs
     *   ]
     */
    public const ENGINES = [
        'data' => [
            'label' => 'Data Intelligence Engine',
            'sources' => ['notify'],
            'produces' => ['Data quality score', 'Pipeline health', 'Integration reliability'],
        ],
        'business' => [
            'label' => 'Business Intelligence Engine',
            'sources' => ['auction', 'hr', 'billing', 'emall'],
            'produces' => ['Business health score', 'Revenue trends', 'Growth signals'],
        ],
        'operational' => [
            'label' => 'Operational Intelligence Engine',
            'sources' => ['tasks', 'ehail', 'hr', 'farms', 'tutor', 'notify', 'design'],
            'produces' => ['Operational efficiency score', 'Process bottlenecks', 'Capacity forecasts'],
        ],
        'financial' => [
            'label' => 'Financial Intelligence Engine',
            'sources' => ['auction', 'billing', 'emall'],
            'produces' => ['Cash flow forecast', 'Cost per unit', 'Profitability by segment'],
        ],
        'people' => [
            'label' => 'People Intelligence Engine',
            'sources' => ['hr', 'tasks', 'tutor'],
            'produces' => ['Workforce efficiency', 'Burnout risk', 'Skill gap map'],
        ],
        'customer' => [
            'label' => 'Customer Intelligence Engine',
            'sources' => ['ehail', 'billing', 'emall', 'pulse'],
            'produces' => ['Customer health score', 'Churn probability', 'Lifetime value'],
        ],
        'document' => [
            'label' => 'Document Intelligence Engine',
            'sources' => [],
            'produces' => ['Expiring contracts', 'Compliance gaps', 'Document risk score'],
        ],
        'community' => [
            'label' => 'Community Intelligence Engine',
            'sources' => ['pulse'],
            'produces' => ['Sentiment trend', 'Feature demand signals', 'Brand health score'],
        ],
        'ai' => [
            'label' => 'AI Intelligence Engine',
            'sources' => [],
            'produces' => ['Agent ROI', 'AI adoption curve', 'Automation effectiveness'],
        ],
        'predictive' => [
            'label' => 'Predictive Intelligence Engine',
            'sources' => ['tasks', 'auction', 'ehail', 'farms', 'billing', 'emall'],
            'produces' => ['Demand forecasts', 'Failure predictions', 'Growth projections'],
        ],
        'decision' => [
            'label' => 'Decision Intelligence Engine',
            'sources' => ['tasks', 'billing', 'emall'],
            'produces' => ['Recommended actions', 'Decision trade-off analysis', 'Priority ranking'],
        ],
        'risk' => [
            'label' => 'Risk Intelligence Engine',
            'sources' => ['hr', 'billing', 'pulse', 'notify'],
            'produces' => ['Risk register', 'Exposure scores', 'Mitigation priorities'],
        ],
        'security' => [
            'label' => 'Security Intelligence Engine',
            'sources' => [],
            'produces' => ['Threat score', 'Compliance posture', 'Access anomaly alerts'],
        ],
        'asset' => [
            'label' => 'Asset Intelligence Engine',
            'sources' => [],
            'produces' => ['Asset health score', 'Replacement forecast', 'Depreciation curves'],
        ],
        'mining' => [
            'label' => 'Mining Intelligence Engine',
            'sources' => [],
            'produces' => ['Production efficiency', 'Cost per ton', 'Equipment utilization', 'Shift performance'],
        ],
        'agriculture' => [
            'label' => 'Agriculture Intelligence Engine',
            'sources' => ['farms'],
            'produces' => ['Yield forecasting', 'Equipment efficiency', 'Input cost tracking', 'Harvest planning'],
        ],
        'construction' => [
            'label' => 'Construction Intelligence Engine',
            'sources' => [],
            'produces' => ['Project cost variance', 'Equipment utilization', 'Labour productivity', 'Contract risk'],
        ],
        'manufacturing' => [
            'label' => 'Manufacturing Intelligence Engine',
            'sources' => [],
            'produces' => ['OEE (Overall Equipment Effectiveness)', 'Defect rate', 'Production throughput', 'Yield analysis'],
        ],
        'retail' => [
            'label' => 'Retail Intelligence Engine',
            'sources' => ['auction', 'emall'],
            'produces' => ['Sell-through rate', 'Basket analysis', 'Seasonal demand forecast', 'Stock optimisation'],
        ],
        'healthcare' => [
            'label' => 'Healthcare Intelligence Engine',
            'sources' => [],
            'produces' => ['Staff utilisation', 'Compliance adherence', 'Asset maintenance compliance', 'Incident trend analysis'],
        ],
        'compliance' => [
            'label' => 'Compliance Intelligence Engine',
            'sources' => [],
            'produces' => ['Compliance gap analysis', 'Regulatory risk score', 'Policy adherence rate', 'Audit readiness score'],
        ],
        'prescriptive' => [
            'label' => 'Prescriptive Intelligence Engine',
            'sources' => ['tasks', 'hr', 'billing', 'emall'],
            'produces' => ['Ranked action recommendations', 'What-if scenario outcomes', 'Optimisation opportunities', 'Decision impact forecasts'],
        ],
    ];

    /**
     * Return all platform definitions.
     */
    public function getPlatformCatalog(): array
    {
        return self::PLATFORMS;
    }

    /**
     * Return all engine definitions.
     */
    public function getEngineRegistry(): array
    {
        return self::ENGINES;
    }

    /**
     * Return the human-readable label for a platform key.
     */
    public function getPlatformLabel(string $key): string
    {
        return self::PLATFORMS[$key]['label'] ?? $key;
    }

    /**
     * Return platform details or null if unknown.
     */
    public function getPlatform(string $key): ?array
    {
        return self::PLATFORMS[$key] ?? null;
    }

    /**
     * Return engine details or null if unknown.
     */
    public function getEngine(string $key): ?array
    {
        return self::ENGINES[$key] ?? null;
    }

    /**
     * Given a set of connected platform keys, return the engines that have
     * at least one platform satisfied.
     */
    public function getActiveEngines(array $connectedPlatforms): array
    {
        $active = [];
        foreach (self::ENGINES as $key => $engine) {
            $overlap = array_intersect($engine['sources'], $connectedPlatforms);
            if (count($overlap) > 0) {
                $active[$key] = array_merge($engine, [
                    'connected_sources' => array_values($overlap),
                    'coverage' => round(count($overlap) / count($engine['sources']) * 100),
                ]);
            }
        }

        return $active;
    }

    /**
     * Build a rich natural-language ecosystem context string for AI prompts.
     * Describes the team's connected platforms and the intelligence they contribute.
     */
    public function buildEcosystemContext(Team $team): string
    {
        $sources = $team->dataSources()->where('status', 'connected')->pluck('platform')->toArray();

        if (empty($sources)) {
            return "No platforms are currently connected for {$team->name}.";
        }

        $lines = ["Connected Dot platforms for {$team->name}:"];

        foreach ($sources as $key) {
            $platform = self::PLATFORMS[$key] ?? null;
            if ($platform) {
                $contributions = implode(', ', array_slice($platform['contributions'], 0, 5));
                $lines[] = "  • {$platform['label']}: contributes {$contributions}";
            } else {
                $lines[] = "  • {$key}";
            }
        }

        $activeEngines = $this->getActiveEngines($sources);
        if ($activeEngines) {
            $lines[] = '';
            $lines[] = 'Active intelligence engines: '.implode(', ', array_map(
                fn ($k, $e) => $e['label']." ({$e['coverage']}% coverage)",
                array_keys($activeEngines),
                $activeEngines,
            ));
        }

        return implode("\n", $lines);
    }

    /**
     * Return the Tailwind CSS colour classes for a platform.
     *
     * Values target the dashboard's dark ink shell (resources/views/layouts/
     * app.blade.php) rather than a white card: low-opacity tinted fills,
     * light-shade text/borders, and a saturated dot. The hue itself is what
     * carries meaning — it is how a connected platform's card is
     * distinguished from its neighbors at a glance — so each platform keeps
     * its own color family, only the shades moved to work on dark backgrounds.
     */
    public function getPlatformColorClasses(string $key): array
    {
        $color = self::PLATFORMS[$key]['color'] ?? 'gray';

        $map = [
            'blue' => ['bg' => 'bg-blue-500/10',    'text' => 'text-blue-300',    'border' => 'border-blue-500/30',    'dot' => 'bg-blue-400'],
            'green' => ['bg' => 'bg-green-500/10',   'text' => 'text-green-300',   'border' => 'border-green-500/30',   'dot' => 'bg-green-400'],
            'purple' => ['bg' => 'bg-purple-500/10',  'text' => 'text-purple-300',  'border' => 'border-purple-500/30',  'dot' => 'bg-purple-400'],
            'yellow' => ['bg' => 'bg-yellow-500/10',  'text' => 'text-yellow-300',  'border' => 'border-yellow-500/30',  'dot' => 'bg-yellow-400'],
            'pink' => ['bg' => 'bg-pink-500/10',     'text' => 'text-pink-300',    'border' => 'border-pink-500/30',    'dot' => 'bg-pink-400'],
            'orange' => ['bg' => 'bg-orange-500/10',  'text' => 'text-orange-300',  'border' => 'border-orange-500/30',  'dot' => 'bg-orange-400'],
            'teal' => ['bg' => 'bg-teal-500/10',    'text' => 'text-teal-300',    'border' => 'border-teal-500/30',    'dot' => 'bg-teal-400'],
            'emerald' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-300', 'border' => 'border-emerald-500/30', 'dot' => 'bg-emerald-400'],
            'red' => ['bg' => 'bg-red-500/10',     'text' => 'text-red-300',     'border' => 'border-red-500/30',     'dot' => 'bg-red-400'],
            'indigo' => ['bg' => 'bg-indigo-500/10',  'text' => 'text-indigo-300',  'border' => 'border-indigo-500/30',  'dot' => 'bg-indigo-400'],
            'cyan' => ['bg' => 'bg-cyan-500/10',    'text' => 'text-cyan-300',    'border' => 'border-cyan-500/30',    'dot' => 'bg-cyan-400'],
            'slate' => ['bg' => 'bg-slate-500/10',   'text' => 'text-slate-300',   'border' => 'border-slate-500/30',   'dot' => 'bg-slate-400'],
            'violet' => ['bg' => 'bg-violet-500/10',  'text' => 'text-violet-300',  'border' => 'border-violet-500/30',  'dot' => 'bg-violet-400'],
            'lime' => ['bg' => 'bg-lime-500/10',     'text' => 'text-lime-300',    'border' => 'border-lime-500/30',    'dot' => 'bg-lime-400'],
            'amber' => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-300',   'border' => 'border-amber-500/30',   'dot' => 'bg-amber-400'],
            'gray' => ['bg' => 'bg-white/5',    'text' => 'text-[var(--mist)]',    'border' => 'border-[var(--line)]',    'dot' => 'bg-gray-400'],
        ];

        return $map[$color] ?? $map['gray'];
    }
}
