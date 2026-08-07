<?php

namespace Database\Seeders;

use App\Models\MetricDefinition;
use App\Services\IntelligenceEngineService;
use Illuminate\Database\Seeder;

/**
 * Seeds metric definitions for every Dot platform and intelligence engine.
 *
 * Each metric definition represents a measurement that Dot.Analytics can
 * compute from one or more connected platform's snapshot data.
 */
class MetricDefinitionSeeder extends Seeder
{
    /**
     * All metric definitions keyed by metric key.
     * Format: key => [label, source_platform, engine, aggregation, unit, description]
     */
    private const METRICS = [
        // ─── Dot.Fleet ────────────────────────────────────────────────────────
        'fleet.health_score' => ['Fleet Health Score', 'dot.fleet', 'asset', 'avg', 'score', 'Composite health score across all fleet vehicles and equipment.'],
        'fleet.cost_per_km' => ['Cost per Kilometre', 'dot.fleet', 'financial', 'avg', 'ZAR/km', 'Total fleet operating cost divided by kilometres travelled.'],
        'fleet.cost_per_ton' => ['Cost per Ton', 'dot.fleet', 'financial', 'avg', 'ZAR/t', 'Total fleet cost divided by tonnage hauled.'],
        'fleet.utilization_rate' => ['Fleet Utilisation Rate', 'dot.fleet', 'operational', 'avg', '%', 'Percentage of scheduled hours where vehicles were actively working.'],
        'fleet.idle_rate' => ['Fleet Idle Rate', 'dot.fleet', 'operational', 'avg', '%', 'Percentage of engine-on time where the vehicle was stationary.'],
        'fleet.fuel_cost' => ['Fuel Cost', 'dot.fleet', 'financial', 'sum', 'ZAR', 'Total fuel expenditure across all vehicles in the period.'],
        'fleet.maintenance_cost' => ['Maintenance Cost', 'dot.fleet', 'financial', 'sum', 'ZAR', 'Total planned and unplanned maintenance costs in the period.'],
        'fleet.driver_score' => ['Driver Efficiency Score', 'dot.fleet', 'operational', 'avg', 'score', 'Composite driver behaviour score (harsh braking, speeding, idling).'],

        // ─── Dot.CRM ──────────────────────────────────────────────────────────
        'crm.customer_ltv' => ['Customer Lifetime Value', 'dot.crm', 'customer', 'avg', 'ZAR', 'Estimated total revenue from a customer over their lifetime.'],
        'crm.win_rate' => ['Deal Win Rate', 'dot.crm', 'business', 'avg', '%', 'Percentage of opportunities that converted to closed-won.'],
        'crm.pipeline_value' => ['Pipeline Value', 'dot.crm', 'financial', 'sum', 'ZAR', 'Total estimated value of all open pipeline opportunities.'],
        'crm.churn_risk_count' => ['Customers at Churn Risk', 'dot.crm', 'risk', 'count', 'customers', 'Number of customers with high churn probability scores.'],
        'crm.lead_quality_score' => ['Lead Quality Score', 'dot.crm', 'predictive', 'avg', 'score', 'AI-scored quality of incoming leads based on historical conversion patterns.'],
        'crm.sales_forecast' => ['Sales Forecast', 'dot.crm', 'predictive', 'sum', 'ZAR', 'Predicted revenue for the next 30 days based on pipeline data.'],

        // ─── Dot.HR ───────────────────────────────────────────────────────────
        'hr.workforce_efficiency' => ['Workforce Efficiency', 'dot.hr', 'people', 'avg', '%', 'Ratio of productive hours to scheduled hours across the workforce.'],
        'hr.absenteeism_rate' => ['Absenteeism Rate', 'dot.hr', 'people', 'avg', '%', 'Percentage of scheduled days missed due to unplanned absence.'],
        'hr.overtime_cost' => ['Overtime Cost', 'dot.hr', 'financial', 'sum', 'ZAR', 'Total overtime payroll expenditure in the period.'],
        'hr.retention_risk_count' => ['Employees at Retention Risk', 'dot.hr', 'risk', 'count', 'employees', 'Number of employees flagged as high flight risk.'],
        'hr.burnout_risk_count' => ['Employees at Burnout Risk', 'dot.hr', 'predictive', 'count', 'employees', 'Employees showing early burnout indicators (excessive overtime, declining performance).'],
        'hr.training_completion' => ['Training Completion Rate', 'dot.hr', 'people', 'avg', '%', 'Percentage of assigned training completed on time.'],
        'hr.skill_gap_score' => ['Skill Gap Score', 'dot.hr', 'people', 'avg', 'score', 'Measured distance between required and available skills across the organisation.'],

        // ─── Dot.Documents ────────────────────────────────────────────────────
        'docs.expiring_contracts' => ['Expiring Contracts', 'dot.documents', 'document', 'count', 'documents', 'Contracts expiring within the next 90 days.'],
        'docs.compliance_risk_score' => ['Compliance Risk Score', 'dot.documents', 'risk', 'avg', 'score', 'Composite score based on missing signatures, overdue reviews, and policy gaps.'],
        'docs.unsigned_docs' => ['Unsigned Documents', 'dot.documents', 'document', 'count', 'documents', 'Documents awaiting required signatures.'],

        // ─── Dot.Hear ─────────────────────────────────────────────────────────
        'hear.sentiment_score' => ['Community Sentiment Score', 'dot.hear', 'community', 'avg', 'score', 'Aggregate sentiment across all community comments and reviews.'],
        'hear.feature_demand_count' => ['Active Feature Requests', 'dot.hear', 'community', 'count', 'requests', 'Number of distinct feature requests with active community engagement.'],
        'hear.brand_health_score' => ['Brand Health Score', 'dot.hear', 'community', 'avg', 'score', 'Composite brand perception index from reviews, sentiment, and engagement.'],

        // ─── Dot.Support ──────────────────────────────────────────────────────
        'support.avg_resolution_time' => ['Average Resolution Time', 'dot.support', 'operational', 'avg', 'hours', 'Mean time to resolve a support ticket.'],
        'support.csat_score' => ['Customer Satisfaction Score', 'dot.support', 'customer', 'avg', 'score', 'Average CSAT rating from resolved tickets.'],
        'support.open_ticket_count' => ['Open Tickets', 'dot.support', 'operational', 'count', 'tickets', 'Number of currently open support tickets.'],
        'support.frustration_index' => ['Customer Frustration Index', 'dot.support', 'risk', 'avg', 'score', 'Composite score based on reopened tickets, escalations, and negative CSAT.'],

        // ─── Dot.Inventory ────────────────────────────────────────────────────
        'inventory.turnover_rate' => ['Inventory Turnover Rate', 'dot.inventory', 'operational', 'avg', 'x/year', 'How many times inventory is sold and replaced in a year.'],
        'inventory.stockout_risk' => ['Items at Stockout Risk', 'dot.inventory', 'risk', 'count', 'items', 'SKUs projected to reach zero stock before next replenishment.'],
        'inventory.dead_stock_value' => ['Dead Stock Value', 'dot.inventory', 'financial', 'sum', 'ZAR', 'Value of inventory not sold in the last 90 days.'],
        'inventory.supplier_risk_count' => ['High-Risk Suppliers', 'dot.inventory', 'risk', 'count', 'suppliers', 'Suppliers with late deliveries, quality issues, or single-source dependency.'],

        // ─── Dot.Payments ─────────────────────────────────────────────────────
        'payments.revenue' => ['Total Revenue', 'dot.payments', 'financial', 'sum', 'ZAR', 'Total revenue received in the period.'],
        'payments.cash_flow' => ['Net Cash Flow', 'dot.payments', 'financial', 'sum', 'ZAR', 'Revenue minus expenses in the period.'],
        'payments.overdue_invoices' => ['Overdue Invoice Value', 'dot.payments', 'risk', 'sum', 'ZAR', 'Total value of invoices past their due date.'],
        'payments.fraud_flag_count' => ['Fraud Flags', 'dot.payments', 'security', 'count', 'events', 'Transactions flagged for potential fraud.'],
        'payments.revenue_forecast' => ['Revenue Forecast', 'dot.payments', 'predictive', 'sum', 'ZAR', 'Predicted revenue for the next 30 days.'],

        // ─── Dot.Security ─────────────────────────────────────────────────────
        'security.risk_score' => ['Security Risk Score', 'dot.security', 'security', 'avg', 'score', 'Composite security posture score across users, systems, and threats.'],
        'security.threat_count' => ['Active Threats', 'dot.security', 'security', 'count', 'threats', 'Number of currently active or unmitigated security threats.'],
        'security.anomalous_logins' => ['Anomalous Login Events', 'dot.security', 'security', 'count', 'events', 'Login events flagged as anomalous in the period.'],

        // ─── Dot.API ──────────────────────────────────────────────────────────
        'api.health_score' => ['API Health Score', 'dot.api', 'data', 'avg', 'score', 'Composite API health based on uptime, latency, and error rate.'],
        'api.error_rate' => ['API Error Rate', 'dot.api', 'operational', 'avg', '%', 'Percentage of API calls resulting in 4xx or 5xx responses.'],
        'api.p95_latency' => ['API P95 Latency', 'dot.api', 'operational', 'avg', 'ms', '95th percentile response time across all API endpoints.'],

        // ─── Dot.Flow ─────────────────────────────────────────────────────────
        'flow.automation_success_rate' => ['Automation Success Rate', 'dot.flow', 'operational', 'avg', '%', 'Percentage of workflow executions that completed successfully.'],
        'flow.failure_count' => ['Workflow Failures', 'dot.flow', 'operational', 'count', 'executions', 'Number of workflow executions that failed in the period.'],
        'flow.automation_roi' => ['Automation ROI', 'dot.flow', 'financial', 'avg', '%', 'Estimated cost savings from automated workflows vs manual processing.'],

        // ─── Dot.Assets ───────────────────────────────────────────────────────
        'assets.health_score' => ['Asset Health Score', 'dot.assets', 'asset', 'avg', 'score', 'Composite health score across all tracked fixed assets.'],
        'assets.replacement_due' => ['Assets Due for Replacement', 'dot.assets', 'predictive', 'count', 'assets', 'Assets projected to require replacement within 12 months.'],
        'assets.utilization_rate' => ['Asset Utilisation Rate', 'dot.assets', 'operational', 'avg', '%', 'Percentage of asset capacity actually being used.'],

        // ─── Dot.Agents ───────────────────────────────────────────────────────
        'agents.adoption_rate' => ['AI Adoption Rate', 'dot.agents', 'ai', 'avg', '%', 'Percentage of eligible tasks being handled by AI agents.'],
        'agents.success_rate' => ['Agent Success Rate', 'dot.agents', 'ai', 'avg', '%', 'Percentage of agent tasks completed successfully without human intervention.'],
        'agents.roi' => ['AI Agent ROI', 'dot.agents', 'financial', 'avg', '%', 'Estimated cost savings and value generated per agent vs manual equivalent.'],

        // ─── Dot.Finance ──────────────────────────────────────────────────────
        'finance.budget_variance' => ['Budget Variance', 'dot.finance', 'financial', 'avg', '%', 'Percentage difference between budgeted and actual spend across cost centres.'],
        'finance.gross_margin' => ['Gross Margin', 'dot.finance', 'financial', 'avg', '%', 'Revenue minus cost of goods sold as a percentage of revenue.'],
        'finance.financial_health' => ['Financial Health Score', 'dot.finance', 'financial', 'avg', 'score', 'Composite financial health score based on liquidity, profitability, and growth.'],

        // ─── Dot.Vault ────────────────────────────────────────────────────────
        'vault.exposure_risk' => ['Credential Exposure Risk', 'dot.vault', 'security', 'avg', 'score', 'Risk score based on credential age, access patterns, and anomaly signals.'],
    ];

    public function run(): void
    {
        foreach (self::METRICS as $key => $definition) {
            MetricDefinition::firstOrCreate(
                ['key' => $key],
                [
                    'label' => $definition[0],
                    'source_platform' => $definition[1],
                    'engine' => $definition[2],
                    'aggregation' => $definition[3],
                    'unit' => $definition[4],
                    'description' => $definition[5],
                ],
            );
        }

        $this->command->info('Seeded '.count(self::METRICS).' metric definitions across '.count(IntelligenceEngineService::PLATFORMS).' platforms.');
    }
}
