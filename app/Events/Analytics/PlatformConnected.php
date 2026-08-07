<?php

namespace App\Events\Analytics;

use App\Models\DataSource;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an organisation successfully connects a new Dot platform.
 * Listeners use this to trigger engine runs, DNA recompute, and audit logging.
 */
class PlatformConnected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DataSource $dataSource,
        public readonly int $teamId,
    ) {}
}
