<?php

namespace App\Console\Commands;

use App\Models\Intelligence\PlatformCheck;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\BriefingBuilder;
use App\Services\Intelligence\Classifier;
use App\Services\Intelligence\Ecosystem;
use App\Services\Intelligence\HealthMonitor;
use App\Services\Intelligence\ReferenceData;
use App\Services\Intelligence\SignalDetector;
use App\Services\Intelligence\SourceRunner;
use App\Support\PageCache;
use Illuminate\Console\Command;

class CollectIntelligence extends Command
{
    protected $signature = 'intelligence:collect {--source= : Collect one source now, whether or not it is due}';

    protected $description = 'Collect public intelligence from the sources that are due, file what arrived, and look for movements';

    public function handle(SourceRunner $runner, ReferenceData $reference, Classifier $classifier, SignalDetector $signals, HealthMonitor $health, Ecosystem $ecosystem, BriefingBuilder $briefings): int
    {
        $reference->install();

        $due = Source::where('is_enabled', true)
            ->when($this->option('source'), fn ($q, $key) => $q->where('key', $key), fn ($q) => $q->where(fn ($d) => $d->whereNull('next_due_at')->orWhere('next_due_at', '<=', now())))
            ->orderBy('next_due_at')
            ->limit($this->option('source') ? 1 : (int) config('intelligence.sources_per_pass'))
            ->get();

        foreach ($due as $source) {
            $run = $runner->run($source, (int) config('intelligence.seconds_per_source'));
            $this->line("{$source->key}: {$run->status} ({$run->created} new, {$run->updated} revised, {$run->unchanged} unchanged, {$run->rejected} refused)");
        }

        if (PlatformCheck::where('checked_at', '>=', now()->subDay())->doesntExist()) {
            $ecosystem->probe();
            PageCache::flush();
        }

        if ($due->isNotEmpty()) {
            $classifier->classifyPending();
            $signals->detect();
            $health->check();
            $briefings->refreshToday();
            PageCache::flush();
        }

        return self::SUCCESS;
    }
}
