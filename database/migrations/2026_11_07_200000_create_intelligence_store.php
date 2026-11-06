<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public business intelligence: where each fact came from, what may be done with it, and when it was last true.
 * No table here has a team_id, and none is reachable from a company's private data.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('intelligence.connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('kind', 24);
            $table->string('homepage_url', 500);
            $table->string('terms_url', 500)->nullable();
            $table->string('licence');
            $table->text('attribution');
            $table->boolean('allows_summary')->default(false);
            $table->boolean('allows_commercial')->default(false);
            $table->text('restrictions')->nullable();
            $table->string('fetcher');
            $table->json('settings')->nullable();
            $table->unsignedInteger('collect_every_minutes');
            $table->unsignedInteger('stale_after_hours');
            $table->boolean('is_enabled')->default(true);
            $table->string('status', 16)->default('new');
            $table->timestamp('next_due_at')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->json('cursor')->nullable();
            $table->timestamps();
        });

        $schema->create('source_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 16)->default('running');
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 500)->nullable();
            $table->index(['source_id', 'started_at']);
        });

        $schema->create('geographies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('iso2', 2)->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind', 16);
            $table->string('region')->nullable()->index();
            $table->boolean('is_featured')->default(false);
        });

        $schema->create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('summary');
            $table->unsignedSmallInteger('sort');
        });

        $schema->create('indicators', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('source_code');
            $table->string('name');
            $table->text('definition');
            $table->string('unit');
            $table->string('frequency', 1);
            $table->unsignedTinyInteger('decimals')->default(1);
            $table->string('good_direction', 8)->default('neutral');
            $table->string('topic', 32)->index();
            $table->unsignedInteger('stale_after_days');
            $table->text('caveat')->nullable();
            $table->json('settings')->nullable();
            $table->string('licence')->nullable();
            $table->string('licence_url', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('licence_checked_at')->nullable();
            $table->unique(['source_id', 'source_code']);
        });

        $schema->create('indicator_industry', function (Blueprint $table) {
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->primary(['indicator_id', 'industry_id']);
            $table->index('industry_id');
        });

        $schema->create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geography_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->double('value');
            $table->foreignId('source_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unique(['indicator_id', 'geography_id', 'period']);
            $table->index(['geography_id', 'indicator_id', 'period']);
        });

        $schema->create('observation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observation_id')->constrained()->cascadeOnDelete();
            $table->double('previous_value');
            $table->double('new_value');
            $table->foreignId('source_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('revised_at');
        });

        $schema->create('items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24)->index();
            $table->string('title', 500);
            $table->text('source_summary')->nullable();
            $table->string('url', 1000);
            $table->char('url_hash', 64)->unique();
            $table->char('title_hash', 64)->index();
            $table->string('external_id')->nullable();
            $table->timestamp('published_at')->index();
            $table->timestamp('collected_at');
            $table->foreignId('duplicate_of')->nullable()->constrained('items')->nullOnDelete();
            $table->text('ai_summary')->nullable();
            $table->json('ai_facts')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->timestamp('classified_at')->nullable()->index();
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason')->nullable();
            $table->unique(['source_id', 'external_id']);
        });

        $schema->create('industry_item', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8);
            $table->primary(['item_id', 'industry_id']);
            $table->index('industry_id');
        });

        $schema->create('geography_item', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geography_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8);
            $table->primary(['item_id', 'geography_id']);
            $table->index('geography_id');
        });

        $schema->create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geography_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->string('kind', 24);
            $table->double('value');
            $table->double('compared_to')->nullable();
            $table->double('size')->default(0);
            $table->timestamp('detected_at')->index();
            $table->unique(['indicator_id', 'geography_id', 'period', 'kind']);
        });

        $schema->create('briefings', function (Blueprint $table) {
            $table->id();
            $table->string('edition', 32);
            $table->date('edition_date');
            $table->string('title');
            $table->json('sections');
            $table->string('written_by', 8);
            $table->timestamp('published_at')->index();
            $table->unique(['edition', 'edition_date']);
        });

        $schema->create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('lei', 20)->nullable()->unique();
            $table->string('cik', 12)->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('geography_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        $schema->create('data_issues', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('kind', 24)->index();
            $table->string('severity', 8);
            $table->string('summary', 500);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        foreach (['data_issues', 'companies', 'briefings', 'signals', 'geography_item', 'industry_item', 'items', 'observation_revisions', 'observations', 'indicator_industry', 'indicators', 'industries', 'geographies', 'source_runs', 'sources'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
