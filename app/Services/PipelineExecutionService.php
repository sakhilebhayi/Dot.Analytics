<?php

namespace App\Services;

use App\Models\DataPipeline;
use App\Models\PipelineRun;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Support\Carbon;

/**
 * Pipeline Execution Service
 *
 * Orchestrates the full ETL/ELT pipeline lifecycle:
 *   1. Extract — read records from the source via the connector
 *   2. Transform — apply mapping, filtering, type coercion, and enrichment rules
 *   3. Load — write transformed records to the destination (analytics snapshots or computed metrics)
 *   4. Audit — update PipelineRun with quality report and lineage trace
 *
 * Supports incremental loads via watermark columns.
 */
class PipelineExecutionService
{
    public function __construct(
        private readonly ConnectorRegistry $connectorRegistry,
    ) {}

    /**
     * Execute a pipeline synchronously.
     * For production use, prefer dispatching IngestPlatformSnapshotJob or a dedicated PipelineJob.
     */
    public function execute(DataPipeline $pipeline, string $trigger = 'manual'): PipelineRun
    {
        $run = PipelineRun::create([
            'data_pipeline_id' => $pipeline->id,
            'status'           => 'running',
            'trigger'          => $trigger,
            'started_at'       => Carbon::now(),
        ]);

        try {
            // 1. Extract
            $watermark = $this->getWatermark($pipeline);
            $extracted = $this->extract($pipeline, $watermark);

            // 2. Transform
            $transformed = $this->transform($extracted['records'], $pipeline->transform_config ?? []);

            // 3. Quality assessment
            $quality = $this->assessQuality($extracted['records'], $transformed);

            // 4. Load
            $writeCount = $this->load($pipeline, $transformed);

            // 5. Persist watermark for next incremental run
            if ($extracted['next_watermark'] !== null) {
                $this->saveWatermark($pipeline, $extracted['next_watermark']);
            }

            $duration = Carbon::now()->diffInMilliseconds($run->started_at);

            $run->update([
                'status'              => 'completed',
                'records_read'        => $extracted['count'],
                'records_written'     => $writeCount,
                'records_failed'      => count($extracted['records']) - count($transformed),
                'data_quality_report' => $quality,
                'lineage'             => $this->buildLineage($pipeline),
                'duration_ms'         => $duration,
                'completed_at'        => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => Carbon::now(),
            ]);
        }

        return $run->fresh();
    }

    // ─── Pipeline stages ───────────────────────────────────────────────────────

    private function extract(DataPipeline $pipeline, mixed $watermark): array
    {
        $config    = $pipeline->source_config ?? [];
        $connector = null;

        if ($pipeline->connector) {
            $connector = $this->connectorRegistry->get($pipeline->connector->type)
                ?? $this->connectorRegistry->forDriver($pipeline->connector->driver);

            if ($connector) {
                // Merge connector-level config with pipeline source config
                $config = array_merge($pipeline->connector->config, $config);
            }
        }

        if (! $connector) {
            // No connector — treat source_config as inline records for testing
            $records = $config['records'] ?? [];
            return ['records' => $records, 'count' => count($records), 'next_watermark' => null];
        }

        return $connector->ingest($config, $watermark);
    }

    /**
     * Apply transformation rules defined in the pipeline's transform_config.
     *
     * Supported operations:
     *   - field_map: rename fields
     *   - filter: keep only records matching conditions
     *   - type_cast: coerce field values to target types
     *   - computed_fields: add derived fields via simple expressions
     *   - drop_fields: remove unwanted columns
     */
    private function transform(array $records, array $config): array
    {
        foreach ($records as &$record) {
            // Field renaming
            if (! empty($config['field_map'])) {
                foreach ($config['field_map'] as $from => $to) {
                    if (array_key_exists($from, $record)) {
                        $record[$to] = $record[$from];
                        unset($record[$from]);
                    }
                }
            }

            // Type casting
            if (! empty($config['type_cast'])) {
                foreach ($config['type_cast'] as $field => $type) {
                    if (isset($record[$field])) {
                        $record[$field] = match ($type) {
                            'int', 'integer'     => (int)   $record[$field],
                            'float', 'decimal'   => (float) $record[$field],
                            'bool', 'boolean'    => filter_var($record[$field], FILTER_VALIDATE_BOOLEAN),
                            'string'             => (string) $record[$field],
                            'date'               => Carbon::parse($record[$field])->toDateString(),
                            'datetime'           => Carbon::parse($record[$field])->toIso8601String(),
                            default              => $record[$field],
                        };
                    }
                }
            }

            // Drop fields
            if (! empty($config['drop_fields'])) {
                foreach ($config['drop_fields'] as $field) {
                    unset($record[$field]);
                }
            }

            // Computed fields
            if (! empty($config['computed_fields'])) {
                foreach ($config['computed_fields'] as $name => $expr) {
                    $record[$name] = $this->evalComputed($expr, $record);
                }
            }
        }
        unset($record);

        // Filtering (after transforms)
        if (! empty($config['filter'])) {
            $records = array_filter($records, fn ($r) => $this->matchesFilter($r, $config['filter']));
            $records = array_values($records);
        }

        return $records;
    }

    /**
     * Load transformed records to the destination.
     * Currently stores as analytics snapshots on the team.
     * Future: write to computed_metrics, knowledge graph nodes, etc.
     */
    private function load(DataPipeline $pipeline, array $records): int
    {
        $dest    = $pipeline->destination_config ?? [];
        $teamId  = $pipeline->team_id;
        $target  = $dest['target'] ?? 'snapshot';

        if ($target === 'snapshot' && $pipeline->data_connector_id) {
            foreach (array_chunk($records, 100) as $chunk) {
                \App\Models\AnalyticsSnapshot::create([
                    'team_id'        => $teamId,
                    'data_source_id' => $pipeline->data_connector_id,
                    'snapshot_type'  => 'pipeline',
                    'payload'        => $chunk,
                    'captured_at'    => Carbon::now(),
                ]);
            }
        }

        return count($records);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function assessQuality(array $raw, array $transformed): array
    {
        $total    = count($raw);
        $passed   = count($transformed);
        $rejected = $total - $passed;

        $nullRatio = 0.0;
        if ($passed > 0) {
            $nullCount = 0;
            array_walk_recursive($transformed, static fn ($v) => $v === null || $v === '' ? $nullCount++ : null);
            $fieldCount = count($transformed[0] ?? []) * $passed;
            $nullRatio  = $fieldCount > 0 ? round($nullCount / $fieldCount, 4) : 0.0;
        }

        return [
            'total_records'     => $total,
            'passed_records'    => $passed,
            'rejected_records'  => $rejected,
            'completeness_pct'  => $total > 0 ? round($passed / $total * 100, 1) : 0,
            'null_ratio'        => $nullRatio,
            'assessed_at'       => Carbon::now()->toIso8601String(),
        ];
    }

    private function buildLineage(DataPipeline $pipeline): array
    {
        return [
            'pipeline_id'   => $pipeline->id,
            'pipeline_name' => $pipeline->name,
            'pipeline_type' => $pipeline->pipeline_type,
            'connector'     => $pipeline->connector?->name,
            'source_type'   => $pipeline->connector?->type,
            'executed_at'   => Carbon::now()->toIso8601String(),
        ];
    }

    private function getWatermark(DataPipeline $pipeline): mixed
    {
        if (! $pipeline->is_incremental) {
            return null;
        }
        $lastRun = $pipeline->runs()
            ->where('status', 'completed')
            ->latest()
            ->first();

        return $lastRun?->data_quality_report['next_watermark'] ?? null;
    }

    private function saveWatermark(DataPipeline $pipeline, mixed $watermark): void
    {
        // Stored in the last completed run's quality report for simplicity
        $run = $pipeline->runs()->where('status', 'completed')->latest()->first();
        if ($run) {
            $report                    = $run->data_quality_report ?? [];
            $report['next_watermark']  = $watermark;
            $run->update(['data_quality_report' => $report]);
        }
    }

    private function evalComputed(string $expr, array $record): mixed
    {
        // Interpolate {field} placeholders with resolved values
        $result = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $record[$m[1]] ?? 0, $expr);

        // Only evaluate if the expression is purely arithmetic (no code injection possible)
        if (preg_match('/^[\d\s\+\-\*\/\.\(\)]+$/', $result)) {
            try {
                return $this->safeArithmetic(trim($result));
            } catch (\Throwable) {
                return 0;
            }
        }

        // Non-arithmetic: return the interpolated string as-is
        return $result;
    }

    /**
     * Safe arithmetic evaluator — recursive descent parser.
     * Supports: +  -  *  /  parentheses  decimal numbers  unary minus.
     * Never uses eval() — parses and evaluates the expression manually.
     */
    private function safeArithmetic(string $expression): float
    {
        $tokens = $this->tokenise($expression);
        $pos    = 0;
        return $this->parseExpr($tokens, $pos);
    }

    /** @return array<array{type:string,val:string|float}> */
    private function tokenise(string $expr): array
    {
        $tokens = [];
        $expr   = preg_replace('/\s+/', '', $expr);
        $len    = strlen($expr);
        $i      = 0;

        while ($i < $len) {
            $c = $expr[$i];
            if (ctype_digit($c) || $c === '.') {
                $num = '';
                while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $num .= $expr[$i++];
                }
                $tokens[] = ['type' => 'num', 'val' => (float) $num];
            } elseif (in_array($c, ['+', '-', '*', '/', '(', ')'], true)) {
                $tokens[] = ['type' => 'op', 'val' => $c];
                $i++;
            } else {
                $i++; // skip unrecognised characters
            }
        }

        return $tokens;
    }

    private function parseExpr(array &$tokens, int &$pos): float
    {
        $left = $this->parseTerm($tokens, $pos);

        while ($pos < count($tokens) && in_array($tokens[$pos]['val'] ?? '', ['+', '-'], true)) {
            $op    = $tokens[$pos++]['val'];
            $right = $this->parseTerm($tokens, $pos);
            $left  = $op === '+' ? $left + $right : $left - $right;
        }

        return $left;
    }

    private function parseTerm(array &$tokens, int &$pos): float
    {
        $left = $this->parseFactor($tokens, $pos);

        while ($pos < count($tokens) && in_array($tokens[$pos]['val'] ?? '', ['*', '/'], true)) {
            $op    = $tokens[$pos++]['val'];
            $right = $this->parseFactor($tokens, $pos);
            $left  = $op === '*' ? $left * $right : ($right != 0 ? $left / $right : 0.0);
        }

        return $left;
    }

    private function parseFactor(array &$tokens, int &$pos): float
    {
        if ($pos >= count($tokens)) {
            return 0.0;
        }

        $tok = $tokens[$pos];

        if ($tok['type'] === 'num') {
            $pos++;
            return (float) $tok['val'];
        }

        if ($tok['val'] === '(') {
            $pos++; // consume '('
            $val = $this->parseExpr($tokens, $pos);
            if (isset($tokens[$pos]) && $tokens[$pos]['val'] === ')') {
                $pos++; // consume ')'
            }
            return $val;
        }

        if ($tok['val'] === '-') {
            $pos++;
            return -$this->parseFactor($tokens, $pos);
        }

        return 0.0;
    }

    private function matchesFilter(array $record, array $filter): bool
    {
        $field    = $filter['field'] ?? null;
        $operator = $filter['operator'] ?? '=';
        $value    = $filter['value'] ?? null;

        if (! $field || ! array_key_exists($field, $record)) {
            return true;
        }

        $fieldVal = $record[$field];

        return match ($operator) {
            '=', '=='   => $fieldVal == $value,
            '!='        => $fieldVal != $value,
            '>'         => $fieldVal > $value,
            '>='        => $fieldVal >= $value,
            '<'         => $fieldVal < $value,
            '<='        => $fieldVal <= $value,
            'contains'  => str_contains((string) $fieldVal, (string) $value),
            'in'        => in_array($fieldVal, (array) $value),
            'not_null'  => $fieldVal !== null && $fieldVal !== '',
            default     => true,
        };
    }
}
