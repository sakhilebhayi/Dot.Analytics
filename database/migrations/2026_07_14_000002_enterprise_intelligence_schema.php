<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External data connectors (databases, REST APIs, files, IoT, etc.)
        Schema::create('data_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');        // rest_api, database, file, webhook, mqtt, kafka, opcua
            $table->string('driver');      // postgres, mysql, sqlserver, oracle, mongodb, bigquery, csv, json, excel
            $table->json('config');        // encrypted connection parameters
            $table->string('status')->default('inactive'); // inactive, testing, active, error
            $table->string('version')->default('1');
            $table->unsignedBigInteger('records_ingested')->default(0);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_ingested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'type', 'status']);
        });

        // ETL/ELT pipeline definitions
        Schema::create('data_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_connector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('pipeline_type')->default('elt'); // etl, elt, cdc, streaming, batch
            $table->json('source_config');       // source schema, tables, queries, filters
            $table->json('transform_config');    // transformation rules, mapping, enrichment
            $table->json('destination_config');  // target tables, models, append/replace
            $table->string('schedule')->nullable(); // cron expression for scheduled runs
            $table->string('status')->default('draft'); // draft, active, paused, archived
            $table->boolean('is_incremental')->default(true);
            $table->string('watermark_column')->nullable(); // for incremental loads
            $table->timestamps();
            $table->index(['team_id', 'status']);
        });

        // Pipeline execution history
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued, running, completed, failed, cancelled
            $table->string('trigger')->default('manual'); // manual, scheduled, event, api
            $table->unsignedBigInteger('records_read')->default(0);
            $table->unsignedBigInteger('records_written')->default(0);
            $table->unsignedBigInteger('records_failed')->default(0);
            $table->json('data_quality_report')->nullable(); // quality checks and results
            $table->json('lineage')->nullable();             // data lineage trace
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['data_pipeline_id', 'status']);
        });

        // AI model usage and cost tracking
        Schema::create('ai_model_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('provider');          // anthropic, openai, google, deepseek, local
            $table->string('model');             // claude-sonnet-4-6, gpt-4o, gemini-1.5-pro, etc.
            $table->string('capability');        // recommendation, query, insight, briefing, sql, classification
            $table->string('engine')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->float('confidence')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'provider', 'created_at']);
        });

        // Immutable audit trail — append-only
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_type')->default('user'); // user, system, agent, api
            $table->string('actor_id')->nullable();
            $table->string('event');                       // platform.connected, insight.dismissed, etc.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            // No updated_at — this is immutable
            $table->index(['team_id', 'event', 'occurred_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        // Stored knowledge graph queries for reuse and sharing
        Schema::create('knowledge_graph_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('question');                     // natural language question
            $table->json('entity_types')->nullable();     // entity types traversed
            $table->json('path')->nullable();             // graph path taken
            $table->text('answer')->nullable();
            $table->float('confidence')->nullable();
            $table->json('evidence')->nullable();         // supporting data points
            $table->unsignedInteger('platforms_consulted')->default(0);
            $table->boolean('is_saved')->default(false);
            $table->timestamps();
            $table->index(['team_id', 'is_saved']);
        });

        // Executive intelligence briefings
        Schema::create('executive_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('period');            // daily, weekly, monthly
            $table->date('period_date');
            $table->string('status')->default('generating'); // generating, ready, delivered, failed
            $table->text('summary')->nullable();
            $table->json('highlights')->nullable();        // key positive signals
            $table->json('risks')->nullable();             // key risks to address
            $table->json('recommendations')->nullable();   // top 3 actions
            $table->json('kpis')->nullable();              // snapshot of key metrics
            $table->json('engines_consulted')->nullable(); // which engines contributed
            $table->unsignedInteger('insight_count')->default(0);
            $table->timestamps();
            $table->unique(['team_id', 'period', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_briefings');
        Schema::dropIfExists('knowledge_graph_queries');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ai_model_usage');
        Schema::dropIfExists('pipeline_runs');
        Schema::dropIfExists('data_pipelines');
        Schema::dropIfExists('data_connectors');
    }
};
