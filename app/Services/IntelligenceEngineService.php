<?php

namespace App\Services;

use App\Models\Team;

/**
 * Master registry of all Dot ecosystem platforms and intelligence engines.
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
     * All Dot ecosystem platforms that can feed the intelligence layer.
     *
     * Format:
     *   'platform_key' => [
     *     'label'            => Human-readable name
     *     'description'      => One-line purpose
     *     'color'             => Tailwind colour name for UI
     *     'contributions'     => Array of data points this platform contributes
     *     'engines'           => Array of engine keys this platform powers
     *     'produces'          => Array of intelligence outputs this platform enables
     *     'real_platform_id'  => The matching platform ID in Dot.Brain's registry
     *                            (brain.platforms.md), or null if no real
     *                            ecosystem platform serves this domain yet.
     *   ]
     *
     * Reconciliation note (wiki.md §7 roadmap item): these 15 keys predate
     * the current 26-platform Dot Ecosystem and use invented names
     * (dot.fleet, dot.crm, etc.) that don't match any real platform ID.
     * Checked each one directly against Dot.Brain's authoritative registry
     * (~/Dot/Dot.Brain/brain.platforms.md) rather than guessing: 11 have a
     * clear, defensible real match; 4 (dot.crm, dot.support, dot.security,
     * dot.vault) genuinely have no analog anywhere in the ecosystem today
     * -- no platform does CRM/pipeline, ticketing/support-desk, dedicated
     * security monitoring, or secrets management. Deliberately additive,
     * not a key rename: the PLATFORMS/ENGINES keys themselves, the 55-row
     * metric seeder, and ~24 existing tests all reference these exact
     * fictitious keys throughout, and a full rename would cascade through
     * all of it -- precisely the "touches core intelligence-engine logic"
     * risk this wiki's own roadmap flags. This field achieves the actual
     * reconciliation goal (which fictitious category maps to which real
     * platform, and which don't map to anything yet) without that blast
     * radius.
     */
    public const PLATFORMS = [
        'dot.fleet' => [
            'label' => 'Dot.Fleet',
            'description' => 'Fleet, vehicle & equipment management',
            'color' => 'blue',
            'real_platform_id' => 'dot-mines', // Mining ERP -- GPS tracking, idle time, engine hours, driver behaviour are all real Dot.Mines equipment-fleet concepts
            'contributions' => [
                'GPS tracking', 'Trip records', 'Fuel consumption', 'Maintenance history',
                'Driver behaviour scores', 'Machine utilization', 'Idle time', 'Engine hours',
                'Route history', 'Equipment health logs',
            ],
            'engines' => ['operational', 'asset', 'financial', 'predictive'],
            'produces' => [
                'Fleet health score', 'Cost per kilometre', 'Cost per ton',
                'Fleet utilization rate', 'Predictive maintenance windows',
                'Driver efficiency ranking', 'Route optimisation signals',
                'Fuel theft detection',
            ],
        ],

        'dot.crm' => [
            'label' => 'Dot.CRM',
            'description' => 'Customer relationship & sales pipeline',
            'color' => 'green',
            'real_platform_id' => null, // no CRM/lead/pipeline platform exists in this ecosystem yet
            'contributions' => [
                'Leads', 'Opportunities', 'Sales records', 'Customer activity',
                'Quotes', 'Closed deals', 'Marketing campaigns',
            ],
            'engines' => ['customer', 'financial', 'predictive', 'risk', 'decision'],
            'produces' => [
                'Customer lifetime value', 'Win probability', 'Sales forecasts',
                'Churn predictions', 'Pipeline health score', 'Lead quality score',
                'Customer sentiment trend',
            ],
        ],

        'dot.hr' => [
            'label' => 'Dot.HR',
            'description' => 'Human resources, workforce & payroll',
            'color' => 'purple',
            'real_platform_id' => 'dot-hr', // exact match
            'contributions' => [
                'Attendance records', 'Leave data', 'Payroll', 'Training completions',
                'Performance reviews', 'Certifications', 'Skills inventory',
            ],
            'engines' => ['people', 'financial', 'predictive', 'risk', 'operational'],
            'produces' => [
                'Workforce efficiency score', 'Burnout prediction', 'Promotion recommendations',
                'Skill gap analysis', 'Department performance', 'Retention risk score',
            ],
        ],

        'dot.documents' => [
            'label' => 'Dot.Documents',
            'description' => 'Document management, contracts & compliance',
            'color' => 'yellow',
            'real_platform_id' => 'dot-engage', // contract sharing + signing is Dot.Engage's exact registered responsibility
            'contributions' => [
                'Contracts', 'Reports', 'Policies', 'SOPs', 'Invoices', 'PDFs',
            ],
            'engines' => ['document', 'risk', 'decision'],
            'produces' => [
                'Expiring contract alerts', 'Missing signature detection',
                'Compliance risk flags', 'Document relationship mapping',
                'AI-generated document summaries', 'Frequently referenced document ranking',
            ],
        ],

        'dot.hear' => [
            'label' => 'Dot.Hear',
            'description' => 'Community, feedback & social listening',
            'color' => 'pink',
            'real_platform_id' => 'dot-pulse', // registered as "Social & community platform"
            'contributions' => [
                'Comments', 'Reviews', 'Discussions', 'Likes', 'Polls',
                'Suggestions', 'Community trends', 'Feature requests',
            ],
            'engines' => ['community', 'customer', 'risk', 'predictive'],
            'produces' => [
                'Sentiment score', 'Trending topics', 'Brand perception index',
                'Feature demand ranking', 'Community health score',
                'Customer satisfaction trend', 'Competitor mentions',
                'Emerging problem signals',
            ],
        ],

        'dot.support' => [
            'label' => 'Dot.Support',
            'description' => 'Customer support, tickets & service desk',
            'color' => 'orange',
            'real_platform_id' => null, // no dedicated ticketing/service-desk platform exists in this ecosystem yet
            'contributions' => [
                'Support tickets', 'Chat transcripts', 'Call records',
                'Resolution times', 'Issue categories', 'CSAT scores',
            ],
            'engines' => ['customer', 'operational', 'predictive', 'risk'],
            'produces' => [
                'Support demand forecast', 'Agent workload prediction',
                'Customer frustration score', 'Recurring issue patterns',
                'Knowledge gap identification',
            ],
        ],

        'dot.inventory' => [
            'label' => 'Dot.Inventory',
            'description' => 'Stock, warehousing & supply chain',
            'color' => 'teal',
            'real_platform_id' => 'dot-emall', // marketplace platform with real Product.stock/warehousing-adjacent data
            'contributions' => [
                'Stock levels', 'Warehouse locations', 'Supplier records',
                'Purchase orders', 'Sales movements',
            ],
            'engines' => ['operational', 'financial', 'predictive', 'risk'],
            'produces' => [
                'Stock shortage forecast', 'Dead stock identification',
                'Supplier risk score', 'Inventory turnover rate',
                'Demand forecasting',
            ],
        ],

        'dot.payments' => [
            'label' => 'Dot.Payments',
            'description' => 'Revenue, transactions & financial flows',
            'color' => 'emerald',
            'real_platform_id' => 'dot-billing', // registered as "Payments & subscriptions" -- exact match
            'contributions' => [
                'Revenue records', 'Expenses', 'Transactions', 'Refunds',
                'Invoices', 'Subscriptions',
            ],
            'engines' => ['financial', 'risk', 'predictive', 'customer'],
            'produces' => [
                'Cash flow analysis', 'Fraud detection signals',
                'Profitability scoring', 'Revenue forecasting',
                'Customer value segmentation',
            ],
        ],

        'dot.security' => [
            'label' => 'Dot.Security',
            'description' => 'Security, access control & threat monitoring',
            'color' => 'red',
            'real_platform_id' => null, // no dedicated security-monitoring platform exists in this ecosystem yet
            'contributions' => [
                'Login events', 'Threat detections', 'Audit logs',
                'Permission changes', 'Suspicious behaviour events',
            ],
            'engines' => ['security', 'risk', 'data'],
            'produces' => [
                'Risk score per user', 'Threat prediction', 'Compliance reports',
                'Security posture index',
            ],
        ],

        'dot.api' => [
            'label' => 'Dot.API',
            'description' => 'API gateway, usage & developer platform',
            'color' => 'indigo',
            'real_platform_id' => 'dot-plug', // registered as "Developer marketplace & extensions" -- closest developer-facing platform
            'contributions' => [
                'API call volumes', 'Latency metrics', 'Error rates',
                'Request patterns', 'Consumer identities',
            ],
            'engines' => ['data', 'operational', 'security', 'predictive'],
            'produces' => [
                'API health score', 'Traffic prediction', 'Bottleneck identification',
                'Abuse detection',
            ],
        ],

        'dot.flow' => [
            'label' => 'Dot.Flow',
            'description' => 'Workflow automation & process orchestration',
            'color' => 'cyan',
            'real_platform_id' => 'dot-tasks', // registered as "Task management" -- step/approval workflow is closest to Dot.Tasks' domain
            'contributions' => [
                'Workflow execution logs', 'Step completions', 'Failure events',
                'Approval records',
            ],
            'engines' => ['operational', 'predictive', 'decision'],
            'produces' => [
                'Workflow optimisation signals', 'Failure prediction',
                'Automation ROI calculation',
            ],
        ],

        'dot.assets' => [
            'label' => 'Dot.Assets',
            'description' => 'Fixed assets, equipment & property management',
            'color' => 'slate',
            'real_platform_id' => 'dot-farms', // registered as "Agriculture ERP" -- ERP-scale equipment/property tracking, distinct from dot.fleet's dot-mines mapping
            'contributions' => [
                'Equipment records', 'Building details', 'Maintenance logs',
                'Ownership records',
            ],
            'engines' => ['asset', 'financial', 'predictive'],
            'produces' => [
                'Asset depreciation curves', 'Replacement prediction',
                'Utilization rate', 'Lifecycle analysis',
            ],
        ],

        'dot.agents' => [
            'label' => 'Dot.Agents',
            'description' => 'AI agents, automation & decision execution',
            'color' => 'violet',
            'real_platform_id' => 'dot-agents', // exact match
            'contributions' => [
                'Agent usage statistics', 'Prompt history', 'Automation success rates',
                'AI confidence scores', 'Execution histories', 'Decision logs',
                'Learning patterns',
            ],
            'engines' => ['ai', 'operational', 'decision'],
            'produces' => [
                'Most valuable agent ranking', 'ROI per agent', 'Failed automation patterns',
                'AI adoption rate', 'Department usage breakdown',
                'Prompt quality score',
            ],
        ],

        'dot.finance' => [
            'label' => 'Dot.Finance',
            'description' => 'Accounting, budgeting & financial reporting',
            'color' => 'lime',
            'real_platform_id' => 'dot-finance', // exact match
            'contributions' => [
                'Budget allocations', 'Actuals vs budget', 'Cost centres',
                'Financial statements', 'Tax records',
            ],
            'engines' => ['financial', 'predictive', 'decision', 'risk'],
            'produces' => [
                'Budget variance alerts', 'Financial health score',
                'Cost optimisation opportunities', 'Profitability by unit',
            ],
        ],

        'dot.vault' => [
            'label' => 'Dot.Vault',
            'real_platform_id' => null, // no dedicated secrets-management platform exists in this ecosystem yet
            'description' => 'Secure credential & secrets management',
            'color' => 'amber',
            'contributions' => [
                'Access events', 'Secret rotation logs', 'Credential usage',
            ],
            'engines' => ['security', 'risk'],
            'produces' => [
                'Credential exposure risk', 'Access anomaly detection',
            ],
        ],
    ];

    /**
     * All 17 intelligence engines.
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
            'sources' => ['dot.api', 'dot.security', 'dot.vault'],
            'produces' => ['Data quality score', 'Pipeline health', 'Integration reliability'],
        ],
        'business' => [
            'label' => 'Business Intelligence Engine',
            'sources' => ['dot.crm', 'dot.hr', 'dot.payments', 'dot.inventory', 'dot.finance'],
            'produces' => ['Business health score', 'Revenue trends', 'Growth signals'],
        ],
        'operational' => [
            'label' => 'Operational Intelligence Engine',
            'sources' => ['dot.fleet', 'dot.inventory', 'dot.support', 'dot.flow', 'dot.hr'],
            'produces' => ['Operational efficiency score', 'Process bottlenecks', 'Capacity forecasts'],
        ],
        'financial' => [
            'label' => 'Financial Intelligence Engine',
            'sources' => ['dot.payments', 'dot.finance', 'dot.fleet', 'dot.hr', 'dot.assets'],
            'produces' => ['Cash flow forecast', 'Cost per unit', 'Profitability by segment'],
        ],
        'people' => [
            'label' => 'People Intelligence Engine',
            'sources' => ['dot.hr', 'dot.agents', 'dot.support'],
            'produces' => ['Workforce efficiency', 'Burnout risk', 'Skill gap map'],
        ],
        'customer' => [
            'label' => 'Customer Intelligence Engine',
            'sources' => ['dot.crm', 'dot.support', 'dot.payments', 'dot.hear'],
            'produces' => ['Customer health score', 'Churn probability', 'Lifetime value'],
        ],
        'document' => [
            'label' => 'Document Intelligence Engine',
            'sources' => ['dot.documents'],
            'produces' => ['Expiring contracts', 'Compliance gaps', 'Document risk score'],
        ],
        'community' => [
            'label' => 'Community Intelligence Engine',
            'sources' => ['dot.hear'],
            'produces' => ['Sentiment trend', 'Feature demand signals', 'Brand health score'],
        ],
        'ai' => [
            'label' => 'AI Intelligence Engine',
            'sources' => ['dot.agents'],
            'produces' => ['Agent ROI', 'AI adoption curve', 'Automation effectiveness'],
        ],
        'predictive' => [
            'label' => 'Predictive Intelligence Engine',
            'sources' => ['dot.fleet', 'dot.crm', 'dot.hr', 'dot.inventory', 'dot.payments', 'dot.support', 'dot.api'],
            'produces' => ['Demand forecasts', 'Failure predictions', 'Growth projections'],
        ],
        'decision' => [
            'label' => 'Decision Intelligence Engine',
            'sources' => ['dot.crm', 'dot.finance', 'dot.agents', 'dot.flow', 'dot.documents'],
            'produces' => ['Recommended actions', 'Decision trade-off analysis', 'Priority ranking'],
        ],
        'risk' => [
            'label' => 'Risk Intelligence Engine',
            'sources' => ['dot.crm', 'dot.payments', 'dot.hr', 'dot.security', 'dot.documents', 'dot.inventory'],
            'produces' => ['Risk register', 'Exposure scores', 'Mitigation priorities'],
        ],
        'security' => [
            'label' => 'Security Intelligence Engine',
            'sources' => ['dot.security', 'dot.vault', 'dot.api'],
            'produces' => ['Threat score', 'Compliance posture', 'Access anomaly alerts'],
        ],
        'asset' => [
            'label' => 'Asset Intelligence Engine',
            'sources' => ['dot.fleet', 'dot.assets'],
            'produces' => ['Asset health score', 'Replacement forecast', 'Depreciation curves'],
        ],
        'mining' => [
            'label' => 'Mining Intelligence Engine',
            'sources' => ['dot.fleet', 'dot.hr', 'dot.assets', 'dot.inventory', 'dot.payments'],
            'produces' => ['Production efficiency', 'Cost per ton', 'Equipment utilization', 'Shift performance'],
        ],
        'agriculture' => [
            'label' => 'Agriculture Intelligence Engine',
            'sources' => ['dot.fleet', 'dot.assets', 'dot.inventory', 'dot.hr'],
            'produces' => ['Yield forecasting', 'Equipment efficiency', 'Input cost tracking', 'Harvest planning'],
        ],
        'construction' => [
            'label' => 'Construction Intelligence Engine',
            'sources' => ['dot.fleet', 'dot.hr', 'dot.assets', 'dot.documents', 'dot.finance'],
            'produces' => ['Project cost variance', 'Equipment utilization', 'Labour productivity', 'Contract risk'],
        ],
        'manufacturing' => [
            'label' => 'Manufacturing Intelligence Engine',
            'sources' => ['dot.assets', 'dot.inventory', 'dot.hr', 'dot.finance', 'dot.flow'],
            'produces' => ['OEE (Overall Equipment Effectiveness)', 'Defect rate', 'Production throughput', 'Yield analysis'],
        ],
        'retail' => [
            'label' => 'Retail Intelligence Engine',
            'sources' => ['dot.inventory', 'dot.crm', 'dot.payments', 'dot.hear'],
            'produces' => ['Sell-through rate', 'Basket analysis', 'Seasonal demand forecast', 'Stock optimisation'],
        ],
        'healthcare' => [
            'label' => 'Healthcare Intelligence Engine',
            'sources' => ['dot.hr', 'dot.documents', 'dot.assets', 'dot.inventory'],
            'produces' => ['Staff utilisation', 'Compliance adherence', 'Asset maintenance compliance', 'Incident trend analysis'],
        ],
        'compliance' => [
            'label' => 'Compliance Intelligence Engine',
            'sources' => ['dot.documents', 'dot.security', 'dot.hr', 'dot.finance', 'dot.vault'],
            'produces' => ['Compliance gap analysis', 'Regulatory risk score', 'Policy adherence rate', 'Audit readiness score'],
        ],
        'prescriptive' => [
            'label' => 'Prescriptive Intelligence Engine',
            'sources' => ['dot.crm', 'dot.fleet', 'dot.hr', 'dot.inventory', 'dot.finance', 'dot.agents'],
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
     * The real Dot.Brain-registered platform ID (per
     * ~/Dot/Dot.Brain/brain.platforms.md) that a fictitious catalog key
     * maps to, or null if no real ecosystem platform serves this domain
     * yet -- see PLATFORMS' own doc comment for the reconciliation this
     * closes (wiki.md §7 roadmap item).
     */
    public function getRealPlatformId(string $key): ?string
    {
        return self::PLATFORMS[$key]['real_platform_id'] ?? null;
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
     */
    public function getPlatformColorClasses(string $key): array
    {
        $color = self::PLATFORMS[$key]['color'] ?? 'gray';

        $map = [
            'blue' => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'border' => 'border-blue-300',    'dot' => 'bg-blue-500'],
            'green' => ['bg' => 'bg-green-100',   'text' => 'text-green-700',   'border' => 'border-green-300',   'dot' => 'bg-green-500'],
            'purple' => ['bg' => 'bg-purple-100',  'text' => 'text-purple-700',  'border' => 'border-purple-300',  'dot' => 'bg-purple-500'],
            'yellow' => ['bg' => 'bg-yellow-100',  'text' => 'text-yellow-700',  'border' => 'border-yellow-300',  'dot' => 'bg-yellow-500'],
            'pink' => ['bg' => 'bg-pink-100',     'text' => 'text-pink-700',    'border' => 'border-pink-300',    'dot' => 'bg-pink-500'],
            'orange' => ['bg' => 'bg-orange-100',  'text' => 'text-orange-700',  'border' => 'border-orange-300',  'dot' => 'bg-orange-500'],
            'teal' => ['bg' => 'bg-teal-100',    'text' => 'text-teal-700',    'border' => 'border-teal-300',    'dot' => 'bg-teal-500'],
            'emerald' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'border-emerald-300', 'dot' => 'bg-emerald-500'],
            'red' => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'border' => 'border-red-300',     'dot' => 'bg-red-500'],
            'indigo' => ['bg' => 'bg-indigo-100',  'text' => 'text-indigo-700',  'border' => 'border-indigo-300',  'dot' => 'bg-indigo-500'],
            'cyan' => ['bg' => 'bg-cyan-100',    'text' => 'text-cyan-700',    'border' => 'border-cyan-300',    'dot' => 'bg-cyan-500'],
            'slate' => ['bg' => 'bg-slate-100',   'text' => 'text-slate-700',   'border' => 'border-slate-300',   'dot' => 'bg-slate-500'],
            'violet' => ['bg' => 'bg-violet-100',  'text' => 'text-violet-700',  'border' => 'border-violet-300',  'dot' => 'bg-violet-500'],
            'lime' => ['bg' => 'bg-lime-100',     'text' => 'text-lime-700',    'border' => 'border-lime-300',    'dot' => 'bg-lime-500'],
            'amber' => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'border' => 'border-amber-300',   'dot' => 'bg-amber-500'],
            'gray' => ['bg' => 'bg-gray-100',    'text' => 'text-gray-700',    'border' => 'border-gray-300',    'dot' => 'bg-gray-500'],
        ];

        return $map[$color] ?? $map['gray'];
    }
}
