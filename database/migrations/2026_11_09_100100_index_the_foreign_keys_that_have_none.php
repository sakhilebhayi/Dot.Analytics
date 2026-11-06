<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MySQL indexes a foreign key for you; SQLite and Postgres do not. These lookups run on every dashboard load. */
return new class extends Migration
{
    private const INDEXES = [
        'report_runs' => ['analytics_report_id'],
        'analytics_dashboards' => ['team_id'],
        'analytics_reports' => ['team_id'],
        'dashboard_widgets' => ['analytics_dashboard_id'],
        'computed_metrics' => ['metric_definition_id'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns) || Schema::hasIndex($table, $columns)) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->index($columns));
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $columns)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($columns));
            }
        }
    }
};
