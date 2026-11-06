<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * A public page costs the database nothing for a repeat visit. The finished page is kept for a few minutes in a file
 * cache of its own, away from sessions and the private data, and is shared by everyone because it knows nobody.
 */
class CachePublicPage
{
    public function handle(Request $request, Closure $next): Response
    {
        // Search is never kept: it is a different page for every question and it is counted.
        if (! $request->isMethod('GET') || ! PageCache::enabled() || $request->routeIs('intel.find')) {
            return $next($request);
        }

        // Only the parameters a page really reads count towards its key, so a made-up query string cannot fill the cache.
        $query = Arr::only($request->query(), ['place', 'industry', 'kind', 'page']);
        ksort($query);

        // A value that is not a plain code is passed through uncached, so made-up values cannot fill the cache either.
        foreach ($query as $value) {
            if (! is_string($value) || ! preg_match('/^[a-z0-9._-]{1,60}$/i', $value)) {
                return $next($request);
            }
        }

        $key = 'page:'.PageCache::generation().':'.sha1($request->getHost().'/'.$request->path().'?'.http_build_query($query));
        $cache = PageCache::store();
        $hit = $cache->get($key);

        if (is_array($hit)) {
            return $this->respond($hit['body'], $hit['type'], $hit['status'], true);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && ! $response->headers->has('Set-Cookie')) {
            $cache->put($key, ['body' => $response->getContent(), 'type' => $response->headers->get('Content-Type', 'text/html; charset=UTF-8'), 'status' => 200], PageCache::SECONDS);
        }

        return $response->getStatusCode() === 200 ? $this->respond($response->getContent(), $response->headers->get('Content-Type', 'text/html; charset=UTF-8'), 200, false) : $response;
    }

    private function respond(string $body, string $type, int $status, bool $hit): Response
    {
        return response($body, $status, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=60', 'X-Library-Cache' => $hit ? 'hit' : 'miss']);
    }
}
