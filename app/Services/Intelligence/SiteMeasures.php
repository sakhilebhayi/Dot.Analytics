<?php

namespace App\Services\Intelligence;

use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * How the public library is used, counted without cookies and without keeping anyone. A visitor is a one-way hash of the
 * address and browser with a salt that changes every week, so it can say how many people came and how many came back within
 * the week, and cannot say who anyone is or follow them past the week. Nothing is sent to anyone else.
 */
class SiteMeasures
{
    /** Pages whose address says what they are; the second part names the thing (an industry, a figure). */
    private const AREAS = ['industries', 'countries', 'indicators', 'newsroom', 'briefings', 'sources', 'find', 'method', 'plans', 'ecosystem'];

    private const BOTS = '/bot|crawl|spider|slurp|preview|monitor|uptime|curl|wget|python|httpclient|headless|facebookexternalhit|bingpreview|pingdom|lighthouse/i';

    public function connection(): Connection
    {
        return DB::connection(config('intelligence.connection'));
    }

    public function shouldCount(Request $request, int $status): bool
    {
        $agent = (string) $request->userAgent();

        return config('intelligence.measure') === true
            && $request->isMethod('GET') && $status === 200
            && $agent !== '' && ! preg_match(self::BOTS, $agent)
            && ! in_array($request->headers->get('Sec-Purpose'), ['prefetch', 'prefetch;prerender'], true)
            && ! in_array($request->path(), ['sitemap.xml', 'robots.txt'], true)
            && ! str_ends_with($request->path(), '.csv');
    }

    /** Never throws: counting must not be able to break a page. */
    public function record(Request $request): void
    {
        try {
            $day = now()->toDateString();
            [$area, $target] = $this->where($request);
            $this->bump($day, $area, $target, 'views');

            $hash = $this->visitor($request);
            $db = $this->connection();

            if ($db->table('visitor_days')->insertOrIgnore(['hash' => $hash, 'day' => $day, 'returning' => 0]) > 0) {
                $this->bump($day, '_all', '', 'visitors');

                // Seen on an earlier day of this same week: a returning visitor. The weekly salt means nothing older matches.
                if ($db->table('visitor_days')->where('hash', $hash)->where('day', '<', $day)->exists()) {
                    $db->table('visitor_days')->where('hash', $hash)->where('day', $day)->update(['returning' => 1]);
                    $this->bump($day, '_all', '', 'returning');
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function referral(string $kind, string $target): void
    {
        try {
            $db = $this->connection();
            $row = ['day' => now()->toDateString(), 'kind' => $kind, 'target' => mb_substr($target, 0, 160)];
            $db->table('referrals')->insertOrIgnore($row + ['clicks' => 0]);
            $db->table('referrals')->where($row)->increment('clicks');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Deletes what is no longer needed: the weekly hashes, old questions and old run records. */
    public function prune(): array
    {
        $db = $this->connection();

        return [
            'visitor_days' => $db->table('visitor_days')->where('day', '<', now()->subDays(8)->toDateString())->delete(),
            'search_log' => $db->table('search_log')->where('day', '<', now()->subDays(90)->toDateString())->delete(),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function where(Request $request): array
    {
        $parts = array_values(array_filter(explode('/', $request->path())));
        $first = $parts[0] ?? '';

        if ($first === '' || $first === '/') {
            return ['home', ''];
        }

        $area = in_array($first, self::AREAS, true) ? $first : 'other';

        return [$area, $area === 'find' ? '' : mb_substr(implode('/', array_slice($parts, 1)), 0, 120)];
    }

    private function bump(string $day, string $area, string $target, string $column): void
    {
        $db = $this->connection();
        $key = ['day' => $day, 'area' => $area, 'target' => $target];
        $db->table('visits')->insertOrIgnore($key + ['views' => 0, 'visitors' => 0, 'returning' => 0]);
        $db->table('visits')->where($key)->increment($column);
    }

    private function visitor(Request $request): string
    {
        $week = now()->format('o-\WW');
        $salt = hash_hmac('sha256', 'visitors:'.$week, (string) config('app.key'));

        return substr(hash_hmac('sha256', $request->ip().'|'.$request->userAgent(), $salt), 0, 40);
    }
}
