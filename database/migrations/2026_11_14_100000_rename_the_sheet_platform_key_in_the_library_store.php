<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The library called Dot.Sheet "sheets"; every other list calls it "sheet". Two tables store that key: the daily
 * reachability check (which decides whether the platform is listed on the public pages) and the count of clicks out to it.
 * Rows are renamed, never deleted, and a row that already uses the right key is left alone.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('intelligence.connection');
    }

    public function up(): void
    {
        $this->rename('sheets', 'sheet');
    }

    public function down(): void
    {
        $this->rename('sheet', 'sheets');
    }

    private function rename(string $from, string $to): void
    {
        $schema = Schema::connection($this->getConnection());
        $db = DB::connection($this->getConnection());

        if ($schema->hasTable('platform_checks') && $db->table('platform_checks')->where('key', $to)->doesntExist()) {
            $db->table('platform_checks')->where('key', $from)->update(['key' => $to]);
        }

        if ($schema->hasTable('referrals')) {
            $taken = $db->table('referrals')->where('kind', 'platform')->where('target', $to)->pluck('day')->all();

            $db->table('referrals')->where('kind', 'platform')->where('target', $from)->whereNotIn('day', $taken)->update(['target' => $to]);
        }
    }
};
