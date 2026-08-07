<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates an OpenAPI 3.0 specification for the Dot.Analytics REST API.
 *
 * Usage:
 *   php artisan analytics:openapi         — outputs to public/api-docs/openapi.json
 *   php artisan analytics:openapi --print — prints to stdout
 *
 * The generated spec documents all /api/v1/* routes with request/response
 * schemas, authentication requirements, rate limit headers, and examples.
 */
class GenerateOpenApiCommand extends Command
{
    protected $signature = 'analytics:openapi {--print : Print to stdout instead of writing to file}';

    protected $description = 'Generate the OpenAPI 3.0 specification for the Dot.Analytics API';

    public function handle(): int
    {
        $spec = $this->buildSpec();
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($this->option('print')) {
            $this->line($json);

            return self::SUCCESS;
        }

        $outputDir = public_path('api-docs');
        $outputFile = $outputDir.'/openapi.json';

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputFile, $json);
        $this->info("OpenAPI spec written to: {$outputFile}");
        $this->line('Serve at: '.url('api-docs/openapi.json'));

        return self::SUCCESS;
    }

    private function buildSpec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Dot.Analytics API',
                'description' => 'The Enterprise Intelligence Platform API for the Dot Ecosystem. Provides cross-platform intelligence, knowledge graph traversal, metric computation, and AI-powered recommendations.',
                'version' => 'v1',
                'contact' => ['name' => 'Dot Ecosystem', 'url' => 'https://infodot.app'],
                'license' => ['name' => 'MIT'],
            ],
            'servers' => [
                ['url' => url('/api'), 'description' => 'Current server'],
                ['url' => 'https://analytics.infodot.app/api', 'description' => 'Production'],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'components' => $this->buildComponents(),
            'paths' => $this->buildPaths(),
            'tags' => $this->buildTags(),
        ];
    }

    private function buildComponents(): array
    {
        return [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Sanctum API Token',
                    'description' => 'Obtain a token via POST /api/v1/tokens/create or from the dashboard.',
                ],
            ],
            'responses' => [
                'Unauthorized' => ['description' => 'Unauthenticated — missing or invalid API token.'],
                'Forbidden' => ['description' => 'Forbidden — insufficient permissions.'],
                'NotFound' => ['description' => 'Resource not found.'],
                'Unprocessable' => ['description' => 'Validation error — check the errors field.'],
                'TooManyRequests' => ['description' => 'Rate limit exceeded. Retry after the X-RateLimit-Reset header value.'],
            ],
            'schemas' => [
                'SuccessResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => true],
                        'message' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                    ],
                ],
                'PaginatedResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean'],
                        'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'current_page' => ['type' => 'integer'],
                                'last_page' => ['type' => 'integer'],
                                'per_page' => ['type' => 'integer'],
                                'total' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
                'CrossPlatformInsight' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'narrative' => ['type' => 'string'],
                        'platforms_involved' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['dot.fleet', 'dot.hr']],
                        'insight_type' => ['type' => 'string', 'enum' => ['correlation', 'causation', 'prediction', 'risk', 'opportunity']],
                        'confidence' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'maximum' => 1],
                        'severity' => ['type' => 'string', 'enum' => ['info', 'warning', 'critical']],
                        'status' => ['type' => 'string', 'enum' => ['new', 'reviewed', 'dismissed']],
                    ],
                ],
                'DataSource' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'platform' => ['type' => 'string', 'example' => 'dot.fleet'],
                        'display_name' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['pending', 'connected', 'error', 'not_connected']],
                        'last_synced_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                ],
                'FeatureFlag' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'enabled_globally' => ['type' => 'boolean'],
                        'rollout_percentage' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        'environment' => ['type' => 'string', 'enum' => ['all', 'production', 'local']],
                    ],
                ],
            ],
        ];
    }

    private function buildPaths(): array
    {
        return [
            // Health
            '/ping' => $this->get('Health', 'Liveness probe — fast, no auth.', 'ping', false),
            '/health' => $this->get('Health', 'Basic health check.', 'health', false),
            '/v1/health/detailed' => $this->get('Health', 'Detailed subsystem health check.', 'health-detailed'),

            // Intelligence
            '/v1/intelligence/engines' => $this->get('Intelligence', 'List all intelligence engines with active status.', 'intelligence-engines'),
            '/v1/intelligence/insights' => $this->getWithParams('Intelligence', 'List cross-platform insights.', 'insights', [
                ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'severity', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['info', 'warning', 'critical']]],
                ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
            ]),
            '/v1/intelligence/run' => $this->post('Intelligence', 'Dispatch intelligence engine jobs.', 'intelligence-run', ['engines' => ['type' => 'array', 'items' => ['type' => 'string']]]),
            '/v1/intelligence/graph' => $this->get('Intelligence', 'Knowledge graph statistics.', 'graph-stats'),
            '/v1/intelligence/graph/traverse' => $this->post('Intelligence', 'Traverse entity relationships in the knowledge graph.', 'graph-traverse', [
                'entity_type' => ['type' => 'string', 'example' => 'customer'],
                'entity_id' => ['type' => 'string', 'example' => 'CUST-001'],
                'max_depth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'default' => 3],
            ]),

            // Platforms
            '/v1/platforms' => $this->get('Platforms', 'List the full Dot platform catalog with connection status.', 'platforms-catalog'),
            '/v1/platforms/connected' => $this->get('Platforms', 'List only connected platforms.', 'platforms-connected'),
            '/v1/platforms/{platform}' => $this->getParam('Platforms', 'Get a connected platform by key.', 'platform-show', 'platform'),
            '/v1/platforms/{platform}/connect' => $this->postParam('Platforms', 'Connect a Dot platform.', 'platform-connect', 'platform', ['base_url' => ['type' => 'string', 'format' => 'uri']]),

            // Metrics
            '/v1/metrics' => $this->get('Metrics', 'List computed metrics for the team.', 'metrics'),
            '/v1/metrics/definitions' => $this->getWithParams('Metrics', 'List metric definitions.', 'metric-defs', [
                ['name' => 'engine', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'platform', 'in' => 'query', 'schema' => ['type' => 'string']],
            ]),
            '/v1/metrics/ai-usage' => $this->get('Metrics', 'AI model usage and cost summary.', 'ai-usage'),

            // Reports
            '/v1/reports/{type}' => $this->getParam('Reports', 'Generate a JSON report. Types: insights, alerts, recommendations, metrics.', 'report-json', 'type'),
            '/v1/reports/{type}/csv' => $this->getParam('Reports', 'Stream a CSV report download.', 'report-csv', 'type'),
            '/v1/reports/{type}/html' => $this->getParam('Reports', 'Get an HTML report for print/PDF.', 'report-html', 'type'),

            // Saved reports
            '/v1/saved-reports' => $this->get('Saved Reports', 'List saved report definitions.', 'saved-reports-list'),
            '/v1/saved-reports/{id}/run' => $this->postParam('Saved Reports', 'Execute a saved report and persist output.', 'saved-report-run', 'id', []),

            // SQL
            '/v1/sql/query' => $this->post('AI SQL', 'Execute a natural-language SQL query against a connected database.', 'sql-query', [
                'question' => ['type' => 'string', 'minLength' => 5],
                'connector_id' => ['type' => 'integer'],
            ]),
            '/v1/sql/generate' => $this->post('AI SQL', 'Generate SQL from natural language without executing it.', 'sql-generate', [
                'question' => ['type' => 'string', 'minLength' => 5],
                'connector_id' => ['type' => 'integer'],
            ]),

            // Ingest
            '/v1/ingest/{platform}' => $this->postParam('Ingest', 'Push a data snapshot from a Dot platform via webhook.', 'ingest', 'platform', []),
            '/v1/ingest/{platform}/ping' => $this->getParam('Ingest', 'Check webhook connectivity for a platform.', 'ingest-ping', 'platform'),

            // Feature flags
            '/v1/feature-flags' => $this->get('Feature Flags', 'List all feature flags.', 'flags-list'),
            '/v1/feature-flags/check/{key}' => $this->getParam('Feature Flags', 'Check if a flag is enabled for the authenticated user.', 'flag-check', 'key'),
            '/v1/feature-flags/{key}/enable' => ['patch' => $this->operation('Feature Flags', 'Enable a feature flag globally.', 'flag-enable')],
            '/v1/feature-flags/{key}/disable' => ['patch' => $this->operation('Feature Flags', 'Disable a feature flag globally.', 'flag-disable')],
            '/v1/feature-flags/{key}/rollout' => ['patch' => $this->operation('Feature Flags', 'Set gradual rollout percentage.', 'flag-rollout')],
        ];
    }

    private function buildTags(): array
    {
        return [
            ['name' => 'Health',        'description' => 'System health and liveness probes'],
            ['name' => 'Intelligence',  'description' => 'Cross-platform intelligence engines and insights'],
            ['name' => 'Platforms',     'description' => 'Dot ecosystem platform connections'],
            ['name' => 'Metrics',       'description' => 'Computed metrics and definitions'],
            ['name' => 'Reports',       'description' => 'Analytics reports (JSON, CSV, HTML)'],
            ['name' => 'Saved Reports', 'description' => 'Persistent report definitions with run history'],
            ['name' => 'AI SQL',        'description' => 'Natural language to SQL query generation'],
            ['name' => 'Ingest',        'description' => 'Webhook data ingestion from Dot platforms'],
            ['name' => 'Feature Flags', 'description' => 'Runtime feature flag management'],
        ];
    }

    // ─── Path builder helpers ─────────────────────────────────────────────────

    private function get(string $tag, string $summary, string $opId, bool $auth = true): array
    {
        return ['get' => $this->operation($tag, $summary, $opId, $auth)];
    }

    private function getWithParams(string $tag, string $summary, string $opId, array $params): array
    {
        $op = $this->operation($tag, $summary, $opId);
        $op['parameters'] = $params;

        return ['get' => $op];
    }

    private function getParam(string $tag, string $summary, string $opId, string $param): array
    {
        $op = $this->operation($tag, $summary, $opId);
        $op['parameters'] = [['name' => $param, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]];

        return ['get' => $op];
    }

    private function post(string $tag, string $summary, string $opId, array $body): array
    {
        $op = $this->operation($tag, $summary, $opId);
        if ($body) {
            $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => $body]]]];
        }

        return ['post' => $op];
    }

    private function postParam(string $tag, string $summary, string $opId, string $param, array $body): array
    {
        $op = $this->operation($tag, $summary, $opId);
        $op['parameters'] = [['name' => $param, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]];
        if ($body) {
            $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => $body]]]];
        }

        return ['post' => $op];
    }

    private function operation(string $tag, string $summary, string $opId, bool $auth = true): array
    {
        $op = [
            'tags' => [$tag],
            'summary' => $summary,
            'operationId' => $opId,
            'responses' => [
                '200' => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessResponse']]]],
                '401' => ['$ref' => '#/components/responses/Unauthorized'],
                '422' => ['$ref' => '#/components/responses/Unprocessable'],
                '429' => ['$ref' => '#/components/responses/TooManyRequests'],
            ],
        ];

        if (! $auth) {
            $op['security'] = [];
        }

        return $op;
    }
}
