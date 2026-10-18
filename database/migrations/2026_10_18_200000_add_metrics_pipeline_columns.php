<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The real data path (docs/metrics-contract.md):
 *
 * - analytics_snapshots gains the contract's own identity: taken_at (when the
 *   platform measured) and period. (data_source_id, period, taken_at) is unique,
 *   which is what makes a replayed snapshot idempotent. captured_at stays the
 *   time WE received it. Rows from before the contract have both NULL and are
 *   never turned into metrics.
 * - computed_metrics gains the comparison to the previous point, the currency of
 *   money values, when the platform observed the value, which snapshot it came
 *   from, and whether that snapshot was seeded demo data.
 * - metric_definitions seeded before the contract described measurements for
 *   platforms that do not exist (dot.fleet, dot.crm ...). Nothing ever computed a
 *   value for them, so those rows are removed; the real catalogue is created by
 *   the metric computation itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_snapshots', function (Blueprint $table) {
            $table->timestamp('taken_at')->nullable()->after('captured_at');
            $table->string('period', 16)->nullable()->after('taken_at');
            $table->unique(['data_source_id', 'period', 'taken_at'], 'analytics_snapshots_contract_identity');
        });

        Schema::table('computed_metrics', function (Blueprint $table) {
            $table->decimal('previous_value', 20, 4)->nullable()->after('value');
            $table->decimal('delta', 20, 4)->nullable()->after('previous_value');
            $table->decimal('delta_pct', 12, 4)->nullable()->after('delta');
            $table->date('previous_period_date')->nullable()->after('period_date');
            $table->string('currency', 3)->nullable()->after('previous_period_date');
            $table->timestamp('observed_at')->nullable()->after('currency');
            $table->unsignedBigInteger('snapshot_id')->nullable()->after('observed_at');
            $table->boolean('is_demo')->default(false)->after('snapshot_id');
            $table->index(['team_id', 'period_date']);
        });

        DB::table('metric_definitions')
            ->where('source_platform', 'like', 'dot.%')
            ->whereNotIn('id', DB::table('computed_metrics')->select('metric_definition_id'))
            ->delete();
    }

    public function down(): void
    {
        Schema::table('computed_metrics', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'period_date']);
            $table->dropColumn(['previous_value', 'delta', 'delta_pct', 'previous_period_date', 'currency', 'observed_at', 'snapshot_id', 'is_demo']);
        });

        Schema::table('analytics_snapshots', function (Blueprint $table) {
            $table->dropUnique('analytics_snapshots_contract_identity');
            $table->dropColumn(['taken_at', 'period']);
        });
    }
};
