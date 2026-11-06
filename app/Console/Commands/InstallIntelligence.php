<?php

namespace App\Console\Commands;

use App\Services\Intelligence\ReferenceData;
use Illuminate\Console\Command;

class InstallIntelligence extends Command
{
    protected $signature = 'intelligence:install';

    protected $description = 'Put the places, industries, sources and series of the public library in place';

    public function handle(ReferenceData $reference): int
    {
        $reference->install();
        $this->info('The public library reference data is in place.');

        return self::SUCCESS;
    }
}
