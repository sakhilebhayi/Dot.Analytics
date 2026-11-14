<?php

namespace App\Services\InfoDot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Asks InfoDot (the identity hub) to prove a handoff token.
 *
 * Production gives every Dot app its own database, so the token InfoDot minted is
 * not in ours. We send it to InfoDot's redeem endpoint with this platform's secret.
 * Returns the identity array on success, null when InfoDot refuses the token (4xx),
 * and throws InfoDotUnavailable when InfoDot cannot be reached or errors (so the
 * caller can say "try again" instead of "forbidden").
 *
 * It also reads which platforms a company has (companyPlatforms), with the same secret and
 * timeout. That read never throws: every failure is a null the page can show plainly.
 */
class IdentityClient
{
    public function enabled(): bool
    {
        return filled(config('ecosystem.infodot.secret')) && filled(config('ecosystem.infodot.redeem_url'));
    }

    /**
     * @return array{user: array<string, mixed>, teams: list<array<string, mixed>>, current_team_id: mixed}|null
     */
    public function redeem(string $token): ?array
    {
        try {
            $response = Http::withToken((string) config('ecosystem.infodot.secret'))
                ->acceptJson()
                ->timeout((int) config('ecosystem.infodot.timeout', 5))
                ->post((string) config('ecosystem.infodot.redeem_url'), [
                    'token' => $token,
                    'platform' => (string) config('ecosystem.infodot.platform'),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('InfoDot redeem unreachable.', ['error' => class_basename($e)]);

            throw new InfoDotUnavailable('InfoDot could not be reached.', 0, $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            Log::warning('InfoDot redeem failed.', ['status' => $response->status()]);

            throw new InfoDotUnavailable('InfoDot is not available.');
        }

        if (! $response->successful()) {
            // 401 means this platform's secret is wrong: an operator problem, worth shouting about.
            Log::log($response->status() === 401 ? 'error' : 'info', 'InfoDot refused a handoff token.', ['status' => $response->status()]);

            return null;
        }

        $data = $response->json();

        return is_array($data) && isset($data['user']['email']) ? $data : null;
    }

    /**
     * What InfoDot records for one company: whether it is a personal workspace, and each discoverable platform with
     * the state the business sees. InfoDot asks the caller to name itself (`platform`) as well as prove its secret.
     *
     * Read-only and never fatal: InfoDot being slow, down, refusing the call, not knowing the company or answering
     * with something unexpected all return null, which the caller shows as "could not be loaded just now". One
     * incomplete entry makes the whole answer unexpected, and nothing is ever assumed to be active.
     *
     * @return array{personal: bool, platforms: list<array{key: string, name: string, status: string, entitled: bool, is_core: bool}>}|null
     */
    public function companyPlatforms(string $teamUuid): ?array
    {
        $base = $this->companiesUrl();
        $caller = (string) config('ecosystem.infodot.platform');

        if (! $this->enabled() || $base === null || $caller === '' || ! Str::isUuid($teamUuid)) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('ecosystem.infodot.secret'))
                ->withHeaders(['X-Dot-Platform' => $caller])
                ->acceptJson()
                ->timeout((int) config('ecosystem.infodot.timeout', 5))
                ->get($base.'/'.$teamUuid.'/platforms', ['platform' => $caller]);
        } catch (ConnectionException $e) {
            Log::warning('InfoDot platforms read unreachable.', ['error' => class_basename($e)]);

            return null;
        }

        if (! $response->successful()) {
            // 401 means this platform's secret is wrong: an operator problem, worth shouting about.
            Log::log($response->status() === 401 ? 'error' : 'warning', 'InfoDot platforms read failed.', ['status' => $response->status()]);

            return null;
        }

        $data = $response->json();
        $rows = is_array($data) ? ($data['platforms'] ?? null) : null;
        $personal = is_array($data) ? ($data['company']['personal'] ?? null) : null;

        if (! is_array($rows) || ! array_is_list($rows) || ! is_bool($personal)) {
            Log::warning('InfoDot platforms read returned an unexpected body.');

            return null;
        }

        $platforms = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['key'] ?? null) || ! is_string($row['name'] ?? null) || ! is_string($row['status'] ?? null)
                || ! is_bool($row['entitled'] ?? null) || preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $row['key']) !== 1) {
                Log::warning('InfoDot platforms read returned an unexpected body.');

                return null;
            }

            $platforms[] = [
                'key' => $row['key'],
                'name' => Str::limit($row['name'], 60, ''),
                'status' => $row['status'],
                'entitled' => $row['entitled'],
                'is_core' => ($row['is_core'] ?? false) === true,
            ];
        }

        return ['personal' => $personal, 'platforms' => $platforms];
    }

    /** Where company reads live. Set outright, or taken from the redeem address so one setting moves both. */
    private function companiesUrl(): ?string
    {
        $explicit = (string) config('ecosystem.infodot.companies_url');

        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        $redeem = (string) config('ecosystem.infodot.redeem_url');

        return preg_match('#/redeem/?$#', $redeem) === 1 ? (string) preg_replace('#/redeem/?$#', '/companies', $redeem) : null;
    }
}
