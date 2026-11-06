<?php

namespace App\Models\Intelligence;

/** What people searched for and whether it found anything, kept without who asked, so failed searches can be fixed. */
class SearchLog extends IntelligenceModel
{
    protected $table = 'search_log';

    public static function record(string $query, int $hits): void
    {
        static::create(['query' => mb_substr(mb_strtolower($query), 0, 160), 'hits' => $hits, 'day' => now()->toDateString(), 'searched_at' => now()]);
    }
}
