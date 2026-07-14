<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cross-platform insights discovered by the intelligence engines
        Schema::create('cross_platform_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('narrative');
            $table->json('platforms_involved');           // ['dot.fleet', 'dot.hr', 'dot.payments']
            $table->json('entities_involved')->nullable(); // ['customer:42', 'employee:7']
            $table->string('insight_type');               // correlation, causation, prediction, risk, opportunity
            $table->float('confidence')->default(0.7);    // 0.0 – 1.0
            $table->string('severity')->default('info');  // info, warning, critical
            $table->string('status')->default('new');     // new, reviewed, dismissed
            $table->json('supporting_metrics')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status', 'severity']);
            $table->index(['team_id', 'insight_type']);
        });

        // Tracks each run of an intelligence engine
        Schema::create('intelligence_engine_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('engine');
            $table->string('status')->default('queued'); // queued, running, completed, failed
            $table->json('platforms_consumed')->nullable();
            $table->unsignedInteger('insights_generated')->default(0);
            $table->unsignedInteger('metrics_computed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'engine', 'status']);
        });

        // Enrich data_sources with what the platform can contribute
        Schema::table('data_sources', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('config');
            $table->timestamp('connected_at')->nullable()->after('last_synced_at');
        });

        // Enrich DNA profiles with deeper behavioural layers
        Schema::table('business_dna_profiles', function (Blueprint $table) {
            $table->json('decision_patterns')->nullable()->after('growth_signals');
            $table->json('customer_behavior')->nullable()->after('decision_patterns');
            $table->json('bottlenecks')->nullable()->after('customer_behavior');
            $table->json('industry_benchmarks')->nullable()->after('bottlenecks');
            $table->float('confidence_score')->default(0.0)->after('industry_benchmarks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_engine_runs');
        Schema::dropIfExists('cross_platform_insights');

        Schema::table('business_dna_profiles', function (Blueprint $table) {
            $table->dropColumn(['decision_patterns', 'customer_behavior', 'bottlenecks', 'industry_benchmarks', 'confidence_score']);
        });

        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn(['capabilities', 'connected_at']);
        });
    }
};
