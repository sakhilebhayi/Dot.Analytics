<?php

namespace App\Services\Intelligence\Fetchers;

use App\Models\Intelligence\DataIssue;

/** One open issue per fingerprint: seeing the same problem again refreshes it instead of adding another. */
class DataIssues
{
    public function raise(string $kind, string $severity, string $summary): DataIssue
    {
        $fingerprint = hash('sha256', $kind.'|'.$summary);
        $issue = DataIssue::where('fingerprint', $fingerprint)->first();

        if ($issue === null) {
            return DataIssue::create(['fingerprint' => $fingerprint, 'kind' => $kind, 'severity' => $severity, 'summary' => mb_substr($summary, 0, 500), 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }

        $issue->update(['last_seen_at' => now(), 'resolved_at' => null]);

        return $issue;
    }

    public function resolve(string $kind, string $summary): void
    {
        DataIssue::where('fingerprint', hash('sha256', $kind.'|'.$summary))->whereNull('resolved_at')->update(['resolved_at' => now()]);
    }
}
