<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before the real data path, a team with no snapshots still got a Business DNA
 * "profile" made of fixed baseline prose ("Baseline pattern established. More
 * data needed ...", a default medium risk profile). It described nothing about
 * the team. Those rows are removed so the panel can say "no data yet"; a
 * profile the model wrote from real data is never touched.
 */
return new class extends Migration
{
    private const BASELINE_SUMMARY = 'Baseline pattern established. More data needed for precise characterisation.';

    public function up(): void
    {
        DB::table('business_dna_profiles')
            ->where('operational_patterns', 'like', '%'.self::BASELINE_SUMMARY.'%')
            ->delete();
    }

    public function down(): void
    {
        // Nothing to restore: the removed rows held no information.
    }
};
