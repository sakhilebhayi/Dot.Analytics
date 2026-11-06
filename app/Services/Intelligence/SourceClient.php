<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Source;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only way out to a source. It reaches hosts named on that source and no others, over https, without following a
 * redirect anywhere else, with short timeouts and a size limit, and it says who is asking.
 */
class SourceClient
{
    /** @param  array<string, scalar>  $query */
    public function get(Source $source, string $url, array $query = []): Response
    {
        $this->assertAllowed($source, $url);

        try {
            $response = Http::withUserAgent((string) config('intelligence.user_agent'))
                ->connectTimeout(5)
                ->timeout((int) config('intelligence.http_timeout', 20))
                ->withOptions(['allow_redirects' => false, 'stream' => false])
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new SourceFailed('The source could not be reached.');
        }

        if ($response->status() === 429) {
            throw new SourceFailed('The source asked us to slow down.', 429, (int) $response->header('Retry-After') ?: null);
        }

        if ($response->redirect()) {
            $next = (string) $response->header('Location');

            return $this->followOnce($source, $next, $url);
        }

        if (! $response->successful()) {
            throw new SourceFailed('The source answered '.$response->status().'.', $response->status());
        }

        if (strlen($response->body()) > (int) config('intelligence.max_response_bytes', 8_000_000)) {
            throw new SourceFailed('The source sent more than we read.', $response->status());
        }

        return $response;
    }

    private function followOnce(Source $source, string $next, string $from): Response
    {
        $target = str_starts_with($next, 'http') ? $next : rtrim((string) preg_replace('~(https://[^/]+).*~', '$1', $from), '/').'/'.ltrim($next, '/');
        $this->assertAllowed($source, $target);

        $response = Http::withUserAgent((string) config('intelligence.user_agent'))
            ->connectTimeout(5)->timeout((int) config('intelligence.http_timeout', 20))
            ->withOptions(['allow_redirects' => false])->get($target);

        if (! $response->successful()) {
            throw new SourceFailed('The source answered '.$response->status().'.', $response->status());
        }

        return $response;
    }

    private function assertAllowed(Source $source, string $url): void
    {
        $parts = parse_url($url);
        $hosts = (array) ($source->settings['hosts'] ?? []);

        if (($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), $hosts, true) || isset($parts['user']) || isset($parts['port'])) {
            throw new SourceFailed('That address is not on this source.');
        }
    }
}
