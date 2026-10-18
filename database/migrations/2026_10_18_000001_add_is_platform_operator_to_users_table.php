<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FeatureFlag is a global (cross-tenant) table, so mutating it is a
 * platform-operator capability, never a team role. Same column and default as
 * Dot.Tutor / Dot.Emall. Granted out of band (php artisan analytics:operator),
 * never through mass assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_operator')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_operator');
        });
    }
};
