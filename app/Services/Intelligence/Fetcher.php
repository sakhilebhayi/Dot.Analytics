<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Source;

/** Reads one kind of source. It knows the source's format and nothing about pages, charts or storage. */
interface Fetcher
{
    /**
     * Hands what it finds to the collector and returns where to carry on next time, so a long source is read over
     * several short runs. Stops early when the deadline passes.
     *
     * @param  array<string, mixed>  $cursor
     * @return array<string, mixed>
     *
     * @throws SourceFailed
     */
    public function fetch(Source $source, Collector $collector, array $cursor, Deadline $deadline): array;
}
