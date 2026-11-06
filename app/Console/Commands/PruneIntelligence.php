<?php

namespace App\Console\Commands;

use App\Models\Intelligence\DataIssue;
use App\Models\Intelligence\Source;
use App\Models\Intelligence\SourceRun;
use App\Services\Intelligence\SiteMeasures;
use Illuminate\Console\Command;

class PruneIntelligence extends Command
{
    protected $signature = 'intelligence:prune';

    protected $description = 'Delete what the library no longer needs: weekly visitor hashes, old questions, old run records and closed issues';

    public function handle(SiteMeasures $measures): int
    {
        $gone = $measures->prune();

        // Keep the last twenty runs of every source, and nothing older than 90 days beyond that.
        $runs = 0;

        foreach (Source::pluck('id') as $id) {
            $cut = SourceRun::where('source_id', $id)->orderByDesc('id')->skip(20)->value('id');

            if ($cut !== null) {
                $runs += SourceRun::where('source_id', $id)->where('id', '<=', $cut)->where('started_at', '<', now()->subDays(90))->delete();
            }
        }

        $issues = DataIssue::whereNotNull('resolved_at')->where('resolved_at', '<', now()->subDays(90))->delete();

        $this->line(json_encode($gone + ['source_runs' => $runs, 'issues' => $issues]));

        return self::SUCCESS;
    }
}
