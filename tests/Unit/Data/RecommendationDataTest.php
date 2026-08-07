<?php

namespace Tests\Unit\Data;

use App\Data\RecommendationData;
use Tests\TestCase;

class RecommendationDataTest extends TestCase
{
    public function test_from_array_creates_dto(): void
    {
        $rec = RecommendationData::fromArray([
            'title' => 'Reduce fleet idle time',
            'rationale' => 'Fleet and HR data show a correlation',
            'engine' => 'operational',
            'priority' => 'high',
            'confidence' => 0.85,
            'platforms_referenced' => ['dot.fleet', 'dot.hr'],
        ]);

        $this->assertEquals('Reduce fleet idle time', $rec->title);
        $this->assertEquals('high', $rec->priority);
        $this->assertEquals(0.85, $rec->confidence);
        $this->assertCount(2, $rec->platformsReferenced);
    }

    public function test_from_array_uses_defaults(): void
    {
        $rec = RecommendationData::fromArray([
            'title' => 'Minimal',
            'rationale' => 'Some reason',
        ]);

        $this->assertEquals('decision', $rec->engine);
        $this->assertEquals('medium', $rec->priority);
        $this->assertEquals(0.7, $rec->confidence);
        $this->assertEmpty($rec->platformsReferenced);
    }

    public function test_to_array_has_snake_case_keys(): void
    {
        $rec = RecommendationData::fromArray(['title' => 'T', 'rationale' => 'R']);
        $array = $rec->toArray();

        $this->assertArrayHasKey('platforms_referenced', $array);
        $this->assertArrayHasKey('supporting_data', $array);
        $this->assertArrayHasKey('action_label', $array);
    }

    public function test_to_array_includes_ai_model_in_supporting_data(): void
    {
        $rec = RecommendationData::fromArray([
            'title' => 'T',
            'rationale' => 'R',
            'ai_model' => 'claude-sonnet-4-6',
        ]);
        $array = $rec->toArray();

        $this->assertEquals('claude-sonnet-4-6', $array['supporting_data']['ai_model']);
    }

    public function test_optional_fields_are_null_by_default(): void
    {
        $rec = RecommendationData::fromArray(['title' => 'T', 'rationale' => 'R']);

        $this->assertNull($rec->actionLabel);
        $this->assertNull($rec->actionUrl);
        $this->assertNull($rec->evidence);
        $this->assertNull($rec->aiModel);
    }
}
