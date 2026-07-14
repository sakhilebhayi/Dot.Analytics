<?php

namespace App\Services\Connectors;

/**
 * File connector — ingests data from CSV, JSON, and Excel (XLSX) files.
 *
 * Files can be supplied as:
 *  - Local filesystem paths
 *  - Base64-encoded content in the config
 *  - HTTP URLs (downloaded on demand)
 */
class FileConnector implements ConnectorInterface
{
    public function getName(): string  { return 'File'; }
    public function getType(): string  { return 'file'; }
    public function getSupportedDrivers(): array { return ['csv', 'json', 'excel', 'xlsx', 'tsv']; }

    public function test(array $config): array
    {
        $path = $this->resolvePath($config);

        if (! $path) {
            return ['success' => false, 'message' => 'No file path, URL, or content provided.'];
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return ['success' => true, 'message' => 'URL-based file (not pre-fetched).', 'latency_ms' => 0];
        }

        if (! file_exists($path)) {
            return ['success' => false, 'message' => "File not found: {$path}"];
        }

        return ['success' => true, 'message' => 'File accessible.', 'latency_ms' => 0];
    }

    public function ingest(array $config, mixed $watermark = null): array
    {
        $driver  = strtolower($config['driver'] ?? 'csv');
        $content = $this->getContent($config);

        if ($content === null) {
            return ['records' => [], 'count' => 0, 'next_watermark' => null];
        }

        $records = match ($driver) {
            'csv', 'tsv' => $this->parseCsv($content, $config),
            'json'       => $this->parseJson($content, $config),
            'excel', 'xlsx' => $this->parseExcel($content),
            default      => [],
        };

        // Apply watermark for incremental loads (row offset or date-based)
        if (is_int($watermark) && $watermark > 0) {
            $records = array_slice($records, $watermark);
        }

        return [
            'records'        => $records,
            'count'          => count($records),
            'next_watermark' => $watermark + count($records),
        ];
    }

    public function getSchema(array $config): array
    {
        $result = $this->ingest($config);
        $first  = $result['records'][0] ?? [];

        return [
            'tables' => [['name' => 'file', 'row_count' => $result['count']]],
            'fields' => array_map(
                fn ($k) => ['name' => $k, 'type' => gettype($first[$k] ?? null)],
                array_keys($first)
            ),
            'sample' => array_slice($result['records'], 0, 5),
        ];
    }

    // ─── Parsers ────────────────────────────────────────────────────────────────

    private function parseCsv(string $content, array $config): array
    {
        $delimiter = $config['delimiter'] ?? ($config['driver'] === 'tsv' ? "\t" : ',');
        $hasHeader = $config['has_header'] ?? true;
        $rows      = [];
        $headers   = [];
        $lines     = explode("\n", trim($content));

        foreach ($lines as $i => $line) {
            if (empty(trim($line))) {
                continue;
            }

            $fields = str_getcsv($line, $delimiter);

            if ($hasHeader && $i === 0) {
                $headers = array_map('trim', $fields);
                continue;
            }

            if ($headers) {
                $row = [];
                foreach ($headers as $j => $header) {
                    $row[$header] = $fields[$j] ?? null;
                }
                $rows[] = $row;
            } else {
                $rows[] = $fields;
            }
        }

        return $rows;
    }

    private function parseJson(string $content, array $config): array
    {
        $data    = json_decode($content, true);
        $path    = $config['records_path'] ?? null;

        if ($path) {
            $data = data_get($data, $path) ?? $data;
        }

        return is_array($data) ? (isset($data[0]) ? $data : [$data]) : [];
    }

    /**
     * Basic XLSX parser — reads the shared strings and first sheet.
     * Handles simple spreadsheets without merged cells or formulas.
     */
    private function parseExcel(string $content): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($tmpFile, $content);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmpFile) !== true) {
                return [];
            }

            // Read shared strings
            $sharedStrings = [];
            $ssXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($ssXml) {
                $ss = new \SimpleXMLElement($ssXml);
                foreach ($ss->si as $si) {
                    $sharedStrings[] = (string) $si->t;
                }
            }

            // Read first sheet
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            if (! $sheetXml) {
                return [];
            }

            $sheet   = new \SimpleXMLElement($sheetXml);
            $rows    = [];
            $headers = [];

            foreach ($sheet->sheetData->row as $rowIdx => $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                    // If type="s", look up shared string
                    if ((string) $cell['t'] === 's') {
                        $value = $sharedStrings[(int) $value] ?? $value;
                    }
                    $cells[] = $value;
                }

                if ($rowIdx === 0) {
                    $headers = $cells;
                } else {
                    $row = [];
                    foreach ($headers as $j => $header) {
                        $row[$header] = $cells[$j] ?? null;
                    }
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            @unlink($tmpFile);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function getContent(array $config): ?string
    {
        // Base64-encoded content
        if (! empty($config['content_base64'])) {
            return base64_decode($config['content_base64']) ?: null;
        }

        // Raw content
        if (! empty($config['content'])) {
            return $config['content'];
        }

        $path = $this->resolvePath($config);
        if (! $path) {
            return null;
        }

        // HTTP URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $ch = curl_init($path);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
            $content = curl_exec($ch);
            curl_close($ch);
            return $content ?: null;
        }

        return file_exists($path) ? file_get_contents($path) : null;
    }

    private function resolvePath(array $config): ?string
    {
        return $config['file_path'] ?? $config['url'] ?? null;
    }
}
