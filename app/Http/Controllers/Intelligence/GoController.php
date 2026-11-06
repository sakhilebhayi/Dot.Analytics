<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\Industry;
use App\Services\Intelligence\Ecosystem;
use App\Services\Intelligence\SiteMeasures;
use Illuminate\Http\RedirectResponse;

/**
 * The only way out of the library to another Dot platform. The address is built here from what we know, never taken from
 * the request, so it cannot be used to send anyone elsewhere, and the click is counted so we can see whether the library
 * leads anyone to the platforms it points at.
 */
class GoController extends Controller
{
    public function __invoke(string $kind, string $slug, Ecosystem $ecosystem, SiteMeasures $measures): RedirectResponse
    {
        $url = match ($kind) {
            'directory', 'directory-verified' => ($industry = Industry::where('slug', $slug)->first()) ? $ecosystem->directoryUrl($industry, $kind === 'directory-verified') : null,
            'infodot' => $slug === 'directory' ? ($ecosystem->infodotUrl() ? rtrim((string) $ecosystem->infodotUrl(), '/').'/businesses' : null) : null,
            'platform' => collect($ecosystem->platforms())->firstWhere('key', $slug)['url'] ?? null,
            default => null,
        };

        abort_if($url === null, 404);
        $measures->referral($kind, $slug);

        return redirect()->away($url, 302, ['Referrer-Policy' => 'strict-origin', 'X-Robots-Tag' => 'noindex']);
    }
}
