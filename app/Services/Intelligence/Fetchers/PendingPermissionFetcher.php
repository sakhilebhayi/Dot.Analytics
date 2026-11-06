<?php

namespace App\Services\Intelligence\Fetchers;

use App\Models\Intelligence\Source;
use App\Services\Intelligence\Collector;
use App\Services\Intelligence\Deadline;
use App\Services\Intelligence\Fetcher;
use App\Services\Intelligence\SourceFailed;

/** A source we want but have no written permission to use. It is listed on the sources page and never collected. */
class PendingPermissionFetcher implements Fetcher
{
    public function fetch(Source $source, Collector $collector, array $cursor, Deadline $deadline): array
    {
        throw new SourceFailed('Waiting for written permission from the publisher.');
    }
}
