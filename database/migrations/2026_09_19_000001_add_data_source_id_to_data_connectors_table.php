<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_connectors', function (Blueprint $table) {
            $table->foreignId('data_source_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('data_connectors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('data_source_id');
        });
    }
};
