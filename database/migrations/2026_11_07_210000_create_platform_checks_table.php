<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('intelligence.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('platform_checks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('url', 500);
            $table->unsignedSmallInteger('status')->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('last_ok_at')->nullable();
        });

        Schema::connection($this->getConnection())->create('search_log', function (Blueprint $table) {
            $table->id();
            $table->string('query', 160);
            $table->unsignedInteger('hits');
            $table->date('day')->index();
            $table->timestamp('searched_at');
        });

        Schema::connection($this->getConnection())->create('visits', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('area', 24);
            $table->string('target', 120)->default('');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('returning')->default(0);
            $table->unique(['day', 'area', 'target']);
        });

        Schema::connection($this->getConnection())->create('visitor_days', function (Blueprint $table) {
            $table->char('hash', 40);
            $table->date('day');
            $table->boolean('returning')->default(false);
            $table->primary(['hash', 'day']);
            $table->index('day');
        });

        Schema::connection($this->getConnection())->create('referrals', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('kind', 24);
            $table->string('target', 160);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['day', 'kind', 'target']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        foreach (['referrals', 'visitor_days', 'visits', 'search_log', 'platform_checks'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
