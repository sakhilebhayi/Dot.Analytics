<?php

namespace Tests\Unit\Data;

use App\Data\IntelligenceContextData;
use Tests\TestCase;

class IntelligenceContextDataTest extends TestCase
{
    public function test_constructs_with_required_fields(): void
    {
        $data = new IntelligenceContextData(
            teamId: 1,
            teamName: 'Acme Corp',
            connectedPlatforms: ['tasks', 'emall'],
            activeEngines: ['operational', 'customer'],
            recentSnapshots: [],
        );

        $this->assertEquals(1, $data->teamId);
        $this->assertEquals('Acme Corp', $data->teamName);
        $this->assertCount(2, $data->connectedPlatforms);
    }

    public function test_platform_count(): void
    {
        $data = new IntelligenceContextData(1, 'Test', ['tasks', 'emall', 'hr'], [], []);
        $this->assertEquals(3, $data->platformCount());
    }

    public function test_engine_count(): void
    {
        $data = new IntelligenceContextData(1, 'Test', [], ['operational', 'financial'], []);
        $this->assertEquals(2, $data->engineCount());
    }

    public function test_has_platform_returns_true_when_present(): void
    {
        $data = new IntelligenceContextData(1, 'Test', ['tasks', 'emall'], [], []);
        $this->assertTrue($data->hasPlatform('tasks'));
    }

    public function test_has_platform_returns_false_when_absent(): void
    {
        $data = new IntelligenceContextData(1, 'Test', ['tasks'], [], []);
        $this->assertFalse($data->hasPlatform('emall'));
    }

    public function test_to_prompt_string_includes_team_name(): void
    {
        $data = new IntelligenceContextData(1, 'Acme Mining', ['tasks'], ['operational'], []);
        $prompt = $data->toPromptString();

        $this->assertStringContainsString('Acme Mining', $prompt);
    }

    public function test_to_prompt_string_includes_platform_count(): void
    {
        $data = new IntelligenceContextData(1, 'Corp', ['tasks', 'emall'], [], []);
        $prompt = $data->toPromptString();

        $this->assertStringContainsString('2', $prompt);
    }

    public function test_to_prompt_string_includes_engine_info_when_available(): void
    {
        $data = new IntelligenceContextData(1, 'Corp', ['tasks'], ['operational', 'asset'], []);
        $prompt = $data->toPromptString();

        $this->assertStringContainsString('2', $prompt); // engine count
    }
}
