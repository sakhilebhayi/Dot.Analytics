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
            connectedPlatforms: ['dot.fleet', 'dot.crm'],
            activeEngines: ['operational', 'customer'],
            recentSnapshots: [],
        );

        $this->assertEquals(1, $data->teamId);
        $this->assertEquals('Acme Corp', $data->teamName);
        $this->assertCount(2, $data->connectedPlatforms);
    }

    public function test_platform_count(): void
    {
        $data = new IntelligenceContextData(1, 'Test', ['dot.fleet', 'dot.crm', 'dot.hr'], [], []);
        $this->assertEquals(3, $data->platformCount());
    }

    public function test_engine_count(): void
    {
        $data = new IntelligenceContextData(1, 'Test', [], ['operational', 'financial'], []);
        $this->assertEquals(2, $data->engineCount());
    }

    public function test_has_platform_returns_true_when_present(): void
    {
        $data = new IntelligenceContextData(1, 'Test', ['dot.fleet', 'dot.crm'], [], []);
        $this->assertTrue($data->hasPlatform('dot.fleet'));
    }

    public function test_has_platform_returns_false_when_absent(): void
    {
        $data = new IntelligenceContextData(1, 'Test', ['dot.fleet'], [], []);
        $this->assertFalse($data->hasPlatform('dot.crm'));
    }

    public function test_to_prompt_string_includes_team_name(): void
    {
        $data = new IntelligenceContextData(1, 'Acme Mining', ['dot.fleet'], ['operational'], []);
        $prompt = $data->toPromptString();

        $this->assertStringContainsString('Acme Mining', $prompt);
    }

    public function test_to_prompt_string_includes_platform_count(): void
    {
        $data = new IntelligenceContextData(1, 'Corp', ['dot.fleet', 'dot.crm'], [], []);
        $prompt = $data->toPromptString();

        $this->assertStringContainsString('2', $prompt);
    }

    public function test_to_prompt_string_includes_engine_info_when_available(): void
    {
        $data = new IntelligenceContextData(1, 'Corp', ['dot.fleet'], ['operational', 'asset'], []);
        $prompt = $data->toPromptString();

        $this->assertStringContainsString('2', $prompt); // engine count
    }
}
