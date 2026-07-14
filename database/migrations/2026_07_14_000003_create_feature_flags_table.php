<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled_globally')->default(false);
            $table->json('enabled_for_teams')->nullable(); // [1, 2, 5] — team IDs
            $table->json('enabled_for_users')->nullable(); // [10, 42] — user IDs
            $table->float('rollout_percentage')->default(0); // 0-100 gradual rollout
            $table->string('environment')->default('all');   // all, production, local
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
