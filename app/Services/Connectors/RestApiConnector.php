<?php

namespace App\Services\Connectors;

/**
 * REST API connector.
 *
 * Connects to any system exposing a JSON REST API.
 * Supports bearer token, API key, and basic authentication.
 * Handles pagination automatically (cursor, page, and offset strategies).
 */
class RestApiConnector implements ConnectorInterface
{
    public function getName(): string  { return 'REST API'; }
    public function getType(): string  { return 'rest_api'; }
    public function getSupportedDrivers(): array { return ['rest', 'http', 'json_api']; }

    public function test(array $config): array
    {
        $url = $config['base_url'] ?? '';
        if (! $url) {
            return ['success' => false, 'message' => 'base_url is required.'];
        }

        try {
            $this->assertNotSsrf($url);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $start = microtime(true);

        $ch = curl_init($url . ($config['health_endpoint'] ?? ''));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $this->buildHeaders($config),
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error   = curl_error($ch);
        curl_close($ch);

        $latency = (int) ((microtime(true) - $start) * 1000);

        return [
            'success'    => $code >= 200 && $code < 400,
            'message'    => $error ?: "HTTP {$code}",
            'latency_ms' => $latency,
        ];
    }

    public function ingest(array $config, mixed $watermark = null): array
    {
        $url        = rtrim($config['base_url'] ?? '', '/');
        $this->assertNotSsrf($url);
        $endpoint   = $config['endpoint'] ?? '';
        $pagination = $config['pagination'] ?? ['strategy' => 'none'];

        $allRecords = [];
        $page       = $pagination['start_page'] ?? 1;
        $cursor     = $watermark;

        do {
            $params = $config['query_params'] ?? [];

            if ($pagination['strategy'] === 'page') {
                $params[$pagination['page_param'] ?? 'page'] = $page;
                $params[$pagination['size_param'] ?? 'per_page'] = $pagination['page_size'] ?? 100;
            } elseif ($pagination['strategy'] === 'cursor' && $cursor) {
                $params[$pagination['cursor_param'] ?? 'cursor'] = $cursor;
            }

            $queryString = $params ? '?' . http_build_query($params) : '';

            $ch = curl_init($url . $endpoint . $queryString);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => $this->buildHeaders($config),
            ]);

            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || ! $body) {
                break;
            }

            $data    = json_decode($body, true);
            $records = data_get($data, $config['records_path'] ?? null) ?? (is_array($data) ? $data : []);

            $allRecords = array_merge($allRecords, $records);

            // Determine next page
            $cursor   = data_get($data, $pagination['next_cursor_path'] ?? 'next_cursor');
            $hasMore  = count($records) >= ($pagination['page_size'] ?? 100);
            $page++;

        } while ($pagination['strategy'] !== 'none' && $hasMore && $cursor !== null && count($allRecords) < 10000);

        return [
            'records'        => $allRecords,
            'count'          => count($allRecords),
            'next_watermark' => $cursor,
        ];
    }

    public function getSchema(array $config): array
    {
        $sample = $this->ingest($config)['records'];
        $first  = $sample[0] ?? [];

        return [
            'tables'  => [['name' => 'records', 'row_count' => count($sample)]],
            'fields'  => array_map(fn ($k) => ['name' => $k, 'type' => gettype($first[$k] ?? null)], array_keys($first)),
            'sample'  => array_slice($sample, 0, 5),
        ];
    }

    private function buildHeaders(array $config): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        $auth = $config['auth'] ?? [];
        if (($auth['type'] ?? '') === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . ($auth['token'] ?? '');
        } elseif (($auth['type'] ?? '') === 'api_key') {
            $headers[] = ($auth['header'] ?? 'X-API-Key') . ': ' . ($auth['key'] ?? '');
        } elseif (($auth['type'] ?? '') === 'basic') {
            $headers[] = 'Authorization: Basic ' . base64_encode(($auth['username'] ?? '') . ':' . ($auth['password'] ?? ''));
        }

        return array_merge($headers, $config['extra_headers'] ?? []);
    }

    /**
     * SSRF protection — blocks requests to private, loopback, and reserved IP ranges.
     * Prevents attackers from using the connector to probe internal infrastructure.
     *
     * @throws \InvalidArgumentException
     */
    private function assertNotSsrf(string $url): void
    {
        if (empty($url)) {
            return;
        }

        $parsed = parse_url($url);
        $host   = $parsed['host'] ?? '';

        if (empty($host)) {
            throw new \InvalidArgumentException("Invalid URL: no host found in '{$url}'.");
        }

        // Resolve the hostname to an IP address
        $ip = gethostbyname($host);

        // If gethostbyname fails it returns the original hostname unchanged
        if ($ip === $host && ! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Cannot resolve hostname '{$host}'.");
        }

        $blocked = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($blocked === false) {
            throw new \InvalidArgumentException(
                "SSRF protection: '{$host}' resolves to a private or reserved address ({$ip}) and cannot be used as a connector endpoint."
            );
        }
    }
}
