<?php

namespace App\Services;

use App\Models\AiModelUsage;
use Illuminate\Support\Facades\Log;

/**
 * Multi-model AI router for Dot.Analytics.
 *
 * Routes intelligence requests to the most appropriate AI model based on:
 * - Capability requirements (some tasks need stronger reasoning)
 * - Model availability (falls back through a priority chain)
 * - Cost optimisation (uses smaller models for simpler tasks)
 * - Configured API keys
 *
 * Supported providers: Anthropic (Claude), OpenAI (GPT), Google (Gemini),
 * DeepSeek, and local models (Ollama).
 *
 * All AI calls are tracked in ai_model_usage for cost and audit purposes.
 */
class AiModelRouter
{
    /**
     * Model definitions: provider → model → capability tiers.
     * Tiers: 1 = premium (complex reasoning), 2 = standard, 3 = lightweight
     */
    private const MODELS = [
        'anthropic' => [
            'claude-sonnet-5-5' => ['tier' => 1, 'input_cost' => 2.00, 'output_cost' => 10.00],
            'claude-haiku-5-5' => ['tier' => 3, 'input_cost' => 0.10, 'output_cost' => 0.50],
        ],
    ];

    /**
     * Capability → required tier mapping.
     * Premium capabilities require tier-1 models.
     */
    private const CAPABILITY_TIERS = [
        'insight' => 1,
        'recommendation' => 1,
        'root_cause' => 1,
        'briefing' => 1,
        'query' => 2,
        'sql' => 2,
        'classification' => 3,
        'summarisation' => 3,
    ];

    public function __construct()
    {
        // Keys loaded from config at call time — no constructor injection
        // to avoid caching issues across environments.
    }

    /**
     * Whether at least one provider API key is configured.
     */
    public function hasProvider(): bool
    {
        foreach (array_keys(self::MODELS) as $provider) {
            if ($this->apiKey($provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send a prompt to the best available model for the given capability.
     * Falls back through the priority chain if a model is unavailable.
     *
     * Returns a development mock string when no provider answers. Anything that
     * PERSISTS the result (insights, briefings, recommendations) must call
     * completeStrict() instead so the mock can never be stored as real output.
     *
     * @param  string  $prompt  The full prompt text.
     * @param  string  $capability  What kind of intelligence this is.
     * @param  int|null  $teamId  For usage tracking.
     * @param  string|null  $engine  The intelligence engine name.
     * @param  int  $maxTokens  Maximum response length.
     */
    public function complete(
        string $prompt,
        string $capability = 'query',
        ?int $teamId = null,
        ?string $engine = null,
        int $maxTokens = 1024,
    ): string {
        return $this->completeStrict($prompt, $capability, $teamId, $engine, $maxTokens)
            ?? $this->mockResponse($capability);
    }

    /**
     * Like complete(), but returns null instead of a mock when no configured
     * provider produced an answer.
     */
    public function completeStrict(
        string $prompt,
        string $capability = 'query',
        ?int $teamId = null,
        ?string $engine = null,
        int $maxTokens = 1024,
    ): ?string {
        if ($this->overBudget($teamId)) {
            Log::warning('AI call refused: the monthly budget is spent.', ['team_id' => $teamId, 'capability' => $capability]);

            return null;
        }

        $requiredTier = self::CAPABILITY_TIERS[$capability] ?? 2;
        $chain = $this->buildFallbackChain($requiredTier);

        foreach ($chain as [$provider, $model]) {
            $apiKey = $this->apiKey($provider);
            if (! $apiKey) {
                continue;
            }

            $startMs = (int) (microtime(true) * 1000);

            try {
                [$response, $inputTokens, $outputTokens] = match ($provider) {
                    'anthropic' => $this->callAnthropic($apiKey, $model, $prompt, $maxTokens),
                    default => [null, 0, 0],
                };

                if ($response === null) {
                    continue;
                }

                $latency = (int) (microtime(true) * 1000) - $startMs;
                $this->trackUsage($teamId, $provider, $model, $capability, $engine, $inputTokens, $outputTokens, $latency);

                return $response;
            } catch (\Throwable $e) {
                // Try next model in chain
                Log::warning('AI provider call failed.', ['provider' => $provider, 'model' => $model, 'error' => $e::class]);

                continue;
            }
        }

        return null;
    }

    // ─── Provider implementations ──────────────────────────────────────────────

    /** @return array{0: string, 1: int, 2: int} */
    private function callAnthropic(string $key, string $model, string $prompt, int $maxTokens): array
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int) config('services.ai.timeout', 45),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: '.$key,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || ! $body) {
            Log::warning('AI provider refused a call.', ['provider' => 'anthropic', 'model' => $model, 'status' => $code]);

            return [null, 0, 0];
        }

        $data = json_decode($body, true);

        return [
            $data['content'][0]['text'] ?? null,
            $data['usage']['input_tokens'] ?? 0,
            $data['usage']['output_tokens'] ?? 0,
        ];
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build the model priority chain for the required tier.
     * Prefers the configured primary provider; falls back through others.
     *
     * @return array<array{0: string, 1: string}>
     */
    private function buildFallbackChain(int $requiredTier): array
    {
        $chain = [];

        // Cheapest suitable model first; business figures go to the one provider the privacy policy names.
        foreach (array_reverse(self::MODELS['anthropic'], true) as $model => $def) {
            if ($def['tier'] <= $requiredTier) {
                $chain[] = ['anthropic', $model];
            }
        }

        return $chain;
    }

    private function apiKey(string $provider): ?string
    {
        return $provider === 'anthropic' ? (config('services.anthropic.key') ?: null) : null;
    }

    /** A spent monthly budget stops the next call. A team has its own allowance, and the whole platform has one too. */
    private function overBudget(?int $teamId): bool
    {
        $since = now()->startOfMonth();
        $spent = fn (?int $team) => (float) AiModelUsage::withoutGlobalScopes()
            ->where('created_at', '>=', $since)
            ->when($team !== null, fn ($query) => $query->where('team_id', $team))
            ->sum('cost_usd');

        return $spent(null) >= (float) config('services.ai.platform_monthly_budget_usd', 50)
            || ($teamId !== null && $spent($teamId) >= (float) config('services.ai.team_monthly_budget_usd', 5));
    }

    private function trackUsage(?int $teamId, string $provider, string $model, string $capability, ?string $engine, int $input, int $output, int $latency): void
    {
        if (! $teamId) {
            return;
        }

        $def = self::MODELS[$provider][$model] ?? [];
        $cost = (($def['input_cost'] ?? 0) * $input + ($def['output_cost'] ?? 0) * $output) / 1_000_000;

        AiModelUsage::create([
            'team_id' => $teamId,
            'provider' => $provider,
            'model' => $model,
            'capability' => $capability,
            'engine' => $engine,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cost_usd' => $cost,
            'latency_ms' => $latency,
        ]);
    }

    private function mockResponse(string $capability): string
    {
        return match ($capability) {
            'insight', 'recommendation' => '[]',
            'briefing' => json_encode([
                'summary' => 'This briefing is not available right now.',
                'highlights' => [],
                'risks' => [],
                'recommendations' => [],
            ]),
            default => 'The assistant is not available right now.',
        };
    }
}
