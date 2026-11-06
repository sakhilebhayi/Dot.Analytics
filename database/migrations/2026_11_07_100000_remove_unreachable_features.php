<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Natural-language SQL, connectors and pipelines could not be reached by anyone and were unsafe if they ever were. The
 * knowledge graph had no writer and feature flags gated nothing. None of these tables can hold anything a person made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['knowledge_graph_queries', 'intelligence_edges', 'intelligence_nodes', 'feature_flags', 'pipeline_runs', 'data_pipelines', 'data_connectors'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void {}
};
