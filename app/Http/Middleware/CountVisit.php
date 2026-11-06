<?php

namespace App\Http\Middleware;

use App\Services\Intelligence\SiteMeasures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Counts a public page view after the page has been sent, so counting never makes anyone wait. */
class CountVisit
{
    public function __construct(private readonly SiteMeasures $measures) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->measures->shouldCount($request, $response->getStatusCode())) {
            $this->measures->record($request);
        }
    }
}
