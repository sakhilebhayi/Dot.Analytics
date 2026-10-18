<?php

namespace Tests\Support;

use App\Services\AiModelRouter;

/**
 * Test double for the AI router. Never touches the network.
 *
 * $configured controls whether a provider "exists"; $response is what the
 * provider returns (null simulates every provider failing).
 */
class FakeAiRouter extends AiModelRouter
{
    /** @var list<string> */
    public array $prompts = [];

    public function __construct(
        public bool $configured = true,
        public ?string $response = '[]',
    ) {
        parent::__construct();
    }

    public function hasProvider(): bool
    {
        return $this->configured;
    }

    public function completeStrict(
        string $prompt,
        string $capability = 'query',
        ?int $teamId = null,
        ?string $engine = null,
        int $maxTokens = 1024,
    ): ?string {
        if (! $this->configured) {
            return null;
        }

        $this->prompts[] = $prompt;

        return $this->response;
    }

    public function complete(
        string $prompt,
        string $capability = 'query',
        ?int $teamId = null,
        ?string $engine = null,
        int $maxTokens = 1024,
    ): string {
        return $this->completeStrict($prompt, $capability, $teamId, $engine, $maxTokens) ?? '';
    }
}
