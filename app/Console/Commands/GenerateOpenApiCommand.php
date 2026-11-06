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
                'description' => 'The Enterprise Intelligence Platform API for the Dot Ecosystem. Provides cross-platform intelligence, metric computation and recommendations for the data a company shares.',
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
                    'description' => 'Tokens are issued by the platform operators; none are issued to the public today.',
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
                        'platforms_involved' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['tasks', 'hr']],
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
                        'platform' => ['type' => 'string', 'example' => 'tasks'],
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

            // Platforms
            '/v1/platforms' => $this->get('Platforms', 'List the full Dot platform catalog with connection status.', 'platforms-catalog'),
            '/v1/platforms/connected' => $this->get('Platforms', 'List only connected platforms.', 'platforms-connected'),
            '/v1/platforms/{platform}' => $this->getParam('Platforms', 'Get a connected platform by key.', 'platform-show', 'platform'),
            '/v1/platforms/{platform}/webhook-secret' => $this->postParam('Platforms', 'Rotate the ingest webhook secret (returned once). Owner/admin only.', 'platform-webhook-secret', 'platform', []),
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

            // Ingest
            '/ingest/snapshots' => $this->serverIngest(),
            '/v1/ingest/{platform}' => $this->postParam('Ingest', 'Push a signed daily metrics snapshot from a Dot platform (see docs/metrics-contract.md). 201 stored, 200 replay of an already-stored snapshot, 404 generic when the company or platform is not connected, 422 when the contract is broken.', 'ingest', 'platform', []),
            '/v1/ingest/{platform}/ping' => $this->getParam('Ingest', 'Check webhook connectivity for a platform.', 'ingest-ping', 'platform'),
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
            ['name' => 'Ingest',        'description' => 'Webhook data ingestion from Dot platforms'],
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

    /** Server to server: no bearer token; the platform signs the body (docs/metrics-contract.md). */
    private function serverIngest(): array
    {
        $op = $this->operation('Ingest', 'A Dot platform publishes its own signed daily metrics snapshot. No user token: send X-InfoDot-Timestamp (unix seconds, 5 minute tolerance) and X-InfoDot-Signature = sha256= + HMAC-SHA256(platform secret, "{timestamp}.{raw body}"). 201 stored, 200 replay (original id), 401 generic for every authentication failure, 404 unknown company, 422 contract broken, 429 rate limited. See docs/metrics-contract.md.', 'ingest-snapshots', false);
        $op['parameters'] = [
            ['name' => 'X-InfoDot-Timestamp', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
            ['name' => 'X-InfoDot-Signature', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
        ];
        $op['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
            'platform' => ['type' => 'string', 'description' => 'The calling platform key, e.g. tasks.'],
            'company_uuid' => ['type' => 'string', 'format' => 'uuid'],
            'taken_at' => ['type' => 'string', 'format' => 'date-time'],
            'period' => ['type' => 'string', 'enum' => ['day']],
            'metrics' => ['type' => 'object'],
        ]]]]];

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
