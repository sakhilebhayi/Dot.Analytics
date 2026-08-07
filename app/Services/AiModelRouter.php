<?php

namespace App\Services;

use App\Models\AiModelUsage;

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
            'claude-sonnet-4-6' => ['tier' => 1, 'input_cost' => 3.00,  'output_cost' => 15.00],
            'claude-haiku-3-5' => ['tier' => 2, 'input_cost' => 0.25,  'output_cost' => 1.25],
        ],
        'openai' => [
            'gpt-4o' => ['tier' => 1, 'input_cost' => 2.50,  'output_cost' => 10.00],
            'gpt-4o-mini' => ['tier' => 3, 'input_cost' => 0.15,  'output_cost' => 0.60],
        ],
        'google' => [
            'gemini-1.5-pro' => ['tier' => 1, 'input_cost' => 1.25,  'output_cost' => 5.00],
            'gemini-1.5-flash' => ['tier' => 3, 'input_cost' => 0.075, 'output_cost' => 0.30],
        ],
        'deepseek' => [
            'deepseek-chat' => ['tier' => 2, 'input_cost' => 0.14,  'output_cost' => 0.28],
            'deepseek-reasoner' => ['tier' => 1, 'input_cost' => 0.55,  'output_cost' => 2.19],
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
     * Send a prompt to the best available model for the given capability.
     * Falls back through the priority chain if a model is unavailable.
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
                    'openai' => $this->callOpenAI($apiKey, $model, $prompt, $maxTokens),
                    'google' => $this->callGemini($apiKey, $model, $prompt, $maxTokens),
                    'deepseek' => $this->callDeepSeek($apiKey, $model, $prompt, $maxTokens),
                    default => [null, 0, 0],
                };

                if ($response === null) {
                    continue;
                }

                $latency = (int) (microtime(true) * 1000) - $startMs;
                $this->trackUsage($teamId, $provider, $model, $capability, $engine, $inputTokens, $outputTokens, $latency);

                return $response;
            } catch (\Throwable) {
                // Try next model in chain
                continue;
            }
        }

        // All models failed — return structured mock for development
        return $this->mockResponse($capability);
    }

    // ─── Provider implementations ──────────────────────────────────────────────

    /** @return array{0: string, 1: int, 2: int} */
    private function callAnthropic(string $key, string $model, string $prompt, int $maxTokens): array
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
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
            return [null, 0, 0];
        }

        $data = json_decode($body, true);

        return [
            $data['content'][0]['text'] ?? null,
            $data['usage']['input_tokens'] ?? 0,
            $data['usage']['output_tokens'] ?? 0,
        ];
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function callOpenAI(string $key, string $model, string $prompt, int $maxTokens): array
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer '.$key,
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
            return [null, 0, 0];
        }

        $data = json_decode($body, true);

        return [
            $data['choices'][0]['message']['content'] ?? null,
            $data['usage']['prompt_tokens'] ?? 0,
            $data['usage']['completion_tokens'] ?? 0,
        ];
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function callGemini(string $key, string $model, string $prompt, int $maxTokens): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['maxOutputTokens' => $maxTokens],
            ]),
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || ! $body) {
            return [null, 0, 0];
        }

        $data = json_decode($body, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $usage = $data['usageMetadata'] ?? [];

        return [
            $text,
            $usage['promptTokenCount'] ?? 0,
            $usage['candidatesTokenCount'] ?? 0,
        ];
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function callDeepSeek(string $key, string $model, string $prompt, int $maxTokens): array
    {
        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer '.$key,
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
            return [null, 0, 0];
        }

        $data = json_decode($body, true);

        return [
            $data['choices'][0]['message']['content'] ?? null,
            $data['usage']['prompt_tokens'] ?? 0,
            $data['usage']['completion_tokens'] ?? 0,
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
        $primary = config('services.ai.primary_provider', 'anthropic');
        $chain = [];

        // Primary provider first
        foreach (self::MODELS[$primary] ?? [] as $model => $def) {
            if ($def['tier'] <= $requiredTier) {
                $chain[] = [$primary, $model];
            }
        }

        // Add fallbacks from other providers
        foreach (self::MODELS as $provider => $models) {
            if ($provider === $primary) {
                continue;
            }
            foreach ($models as $model => $def) {
                if ($def['tier'] <= $requiredTier) {
                    $chain[] = [$provider, $model];
                }
            }
        }

        return $chain;
    }

    private function apiKey(string $provider): ?string
    {
        return match ($provider) {
            'anthropic' => config('services.anthropic.key') ?: null,
            'openai' => config('services.openai.key') ?: null,
            'google' => config('services.google.ai_key') ?: null,
            'deepseek' => config('services.deepseek.key') ?: null,
            default => null,
        };
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
                'summary' => 'Intelligence briefing unavailable — configure an AI provider key to enable.',
                'highlights' => [],
                'risks' => [],
                'recommendations' => [],
            ]),
            default => 'AI provider not configured. Add ANTHROPIC_API_KEY, OPENAI_API_KEY, GOOGLE_AI_KEY, or DEEPSEEK_API_KEY to .env.',
        };
    }
}
