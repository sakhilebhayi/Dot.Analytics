<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The finished public pages, kept for a few minutes. Every key carries a generation number, so changing the generation
 * retires every page at once: used whenever something is collected, and whenever an item is taken down, so a takedown
 * does not wait for the clock.
 */
final class PageCache
{
    public const SECONDS = 300;

    public static function store(): Repository
    {
        return Cache::store(config('intelligence.page_cache_store', 'pages'));
    }

    public static function enabled(): bool
    {
        return (bool) config('intelligence.page_cache', true);
    }

    public static function generation(): int
    {
        return (int) self::store()->get('page-generation', 1);
    }

    public static function flush(): void
    {
        self::store()->forever('page-generation', self::generation() + 1);
    }
}
