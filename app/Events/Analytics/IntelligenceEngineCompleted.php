<?php

namespace App\Events\Analytics;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the team's private channel when an intelligence engine completes.
 * The dashboard listens and refreshes widget data in real-time without a reload.
 */
class IntelligenceEngineCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $teamId,
        public readonly string $engine,
        public readonly int $insightsGenerated,
        public readonly int $metricsComputed,
        public readonly array $platformsConsumed,
        public readonly int $runId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("team.{$this->teamId}.intelligence")];
    }

    public function broadcastAs(): string
    {
        return 'engine.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'engine' => $this->engine,
            'insights_generated' => $this->insightsGenerated,
            'platforms_consumed' => $this->platformsConsumed,
            'run_id' => $this->runId,
        ];
    }
}
