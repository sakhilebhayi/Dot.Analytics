<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Library\AlertScanner;
use Illuminate\Console\Command;

class ScanLibraryAlerts extends Command
{
    protected $signature = 'library:scan';

    protected $description = 'Record, for each person who watches something, what the library collected that touches it';

    public function handle(AlertScanner $scanner): int
    {
        $written = 0;

        User::whereHas('libraryWatches')->chunkById(100, function ($users) use ($scanner, &$written) {
            foreach ($users as $user) {
                $written += $scanner->scan($user);
            }
        });

        $this->line("{$written} alerts written");

        return self::SUCCESS;
    }
}
