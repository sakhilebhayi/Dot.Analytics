<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a signed-in person keeps in the library: what they watch, what they saved, what the library told them, and how they
 * want to hear from it. It belongs to the person, not to a company, and goes when the account goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('key', 120);
            $table->string('place', 8)->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'kind', 'key', 'place']);
        });

        Schema::create('library_saved', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('ref', 190);
            $table->string('title', 300);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'kind', 'ref']);
        });

        Schema::create('library_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('ref', 190);
            $table->string('title', 500);
            $table->string('detail', 300)->nullable();
            $table->string('href', 300);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->unique(['user_id', 'kind', 'ref']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('library_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('digest', 8)->default('off');
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamp('last_digest_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['library_preferences', 'library_alerts', 'library_saved', 'library_watches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
