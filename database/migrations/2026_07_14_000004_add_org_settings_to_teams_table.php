<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('personal_team');
            $table->string('locale', 10)->default('en')->after('currency');
            $table->string('timezone', 64)->default('UTC')->after('locale');
            $table->string('industry', 100)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['currency', 'locale', 'timezone', 'industry']);
        });
    }
};
