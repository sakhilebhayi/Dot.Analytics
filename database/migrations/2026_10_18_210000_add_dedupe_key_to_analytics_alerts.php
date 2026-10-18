<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An alert raised by the metric anomaly detector is identified by
 * "anomaly:{metric definition}:{day}", so the same anomaly is raised once per
 * metric per day however many times the (hourly) computation runs. NULL for
 * every other kind of alert, which stay unconstrained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_alerts', function (Blueprint $table) {
            $table->string('dedupe_key', 120)->nullable()->after('context');
            $table->unique(['team_id', 'dedupe_key'], 'analytics_alerts_team_dedupe');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_alerts', function (Blueprint $table) {
            $table->dropUnique('analytics_alerts_team_dedupe');
            $table->dropColumn('dedupe_key');
        });
    }
};
