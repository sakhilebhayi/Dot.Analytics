<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Industry;
use App\Models\Intelligence\PlatformCheck;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * What the library says about InfoDot and the platforms around it. Platforms are offered only if they answered a check in
 * the last three days, so no page ever links to something that is down or gone. The directory is reached through its public
 * search by category, which respects each business's own visibility and verification, never through a copy of its data.
 */
class Ecosystem
{
    private const FRESH_DAYS = 3;

    /** Asks each platform's address once. Slow platforms are given a short time, then recorded as not answering. */
    public function probe(): int
    {
        $ok = 0;

        foreach ((array) config('intelligence_ecosystem.platforms') as $platform) {
            try {
                $status = Http::withUserAgent((string) config('intelligence.user_agent'))->connectTimeout(4)->timeout(8)->withOptions(['allow_redirects' => false])->get($platform['url'])->status();
            } catch (Throwable) {
                $status = null;
            }

            $answered = $status !== null && $status >= 200 && $status < 400;
            $check = PlatformCheck::firstOrNew(['key' => $platform['key']]);
            $check->fill(['url' => $platform['url'], 'status' => $status, 'checked_at' => now()]);

            if ($answered) {
                $check->last_ok_at = now();
                $ok++;
            }

            $check->save();
        }

        return $ok;
    }

    /** @return list<array{key: string, name: string, url: string, does: string}> */
    public function platforms(): array
    {
        $live = PlatformCheck::where('last_ok_at', '>=', now()->subDays(self::FRESH_DAYS))->whereBetween('status', [200, 399])->pluck('key')->all();

        return array_values(array_filter((array) config('intelligence_ecosystem.platforms'), fn ($p) => in_array($p['key'], $live, true)));
    }

    public function infodotAvailable(): bool
    {
        return collect($this->platforms())->contains('key', 'infodot');
    }

    /** The InfoDot directory's own search for the businesses of an industry, or null when InfoDot is not answering. */
    public function directoryUrl(Industry $industry, bool $verifiedOnly = false): ?string
    {
        $rule = config('intelligence_ecosystem.directory.'.$industry->slug);

        if ($rule === null || ! $this->infodotAvailable()) {
            return null;
        }

        return rtrim((string) config('intelligence_ecosystem.infodot'), '/').'/businesses?'.http_build_query($rule + ($verifiedOnly ? ['verified' => 1] : []));
    }

    public function infodotUrl(): ?string
    {
        return $this->infodotAvailable() ? (string) config('intelligence_ecosystem.infodot') : null;
    }
}
