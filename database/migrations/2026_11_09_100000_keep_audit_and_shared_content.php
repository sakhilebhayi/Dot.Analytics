<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two things that were destroyed by a delete they should have survived.
 *
 * The audit trail no longer disappears with its team: the rows keep the id of the team that existed. And a report or a
 * dashboard no longer goes when the one member who made it deletes their account: it stays with the team, with no author.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        foreach (['analytics_reports', 'analytics_dashboards'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Deliberately not reversible: restoring the cascade would bring back the loss this migration removes.
    }
};
