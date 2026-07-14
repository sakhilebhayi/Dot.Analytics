<?php

namespace Tests\Unit\Data;

use App\Data\CrossPlatformInsightData;
use Tests\TestCase;

class CrossPlatformInsightDataTest extends TestCase
{
    public function test_from_array_creates_correct_dto(): void
    {
        $data = [
            'title'              => 'Test insight',
            'narrative'          => 'Detailed narrative',
            'platforms_involved' => ['dot.fleet', 'dot.hr'],
            'insight_type'       => 'causation',
            'confidence'         => 0.85,
            'severity'           => 'warning',
        ];

        $dto = CrossPlatformInsightData::fromArray($data);

        $this->assertEquals('Test insight', $dto->title);
        $this->assertEquals('causation', $dto->insightType);
        $this->assertEquals(0.85, $dto->confidence);
        $this->assertEquals('warning', $dto->severity);
        $this->assertEquals(['dot.fleet', 'dot.hr'], $dto->platformsInvolved);
    }

    public function test_from_array_uses_defaults_for_missing_fields(): void
    {
        $dto = CrossPlatformInsightData::fromArray([
            'title'     => 'Minimal',
            'narrative' => 'Some narrative',
        ]);

        $this->assertEquals('correlation', $dto->insightType);
        $this->assertEquals(0.7, $dto->confidence);
        $this->assertEquals('info', $dto->severity);
        $this->assertEmpty($dto->platformsInvolved);
    }

    public function test_is_critical_returns_true_for_critical_severity(): void
    {
        $dto = CrossPlatformInsightData::fromArray([
            'title'     => 'Critical',
            'narrative' => 'Critical narrative',
            'severity'  => 'critical',
        ]);

        $this->assertTrue($dto->isCritical());
    }

    public function test_is_critical_returns_false_for_info_severity(): void
    {
        $dto = CrossPlatformInsightData::fromArray([
            'title'     => 'Info',
            'narrative' => 'Info narrative',
            'severity'  => 'info',
        ]);

        $this->assertFalse($dto->isCritical());
    }

    public function test_is_high_confidence_returns_true_for_08_or_above(): void
    {
        $dto = CrossPlatformInsightData::fromArray([
            'title'      => 'High confidence',
            'narrative'  => '...',
            'confidence' => 0.9,
        ]);

        $this->assertTrue($dto->isHighConfidence());
    }

    public function test_is_high_confidence_returns_false_below_08(): void
    {
        $dto = CrossPlatformInsightData::fromArray([
            'title'      => 'Low confidence',
            'narrative'  => '...',
            'confidence' => 0.6,
        ]);

        $this->assertFalse($dto->isHighConfidence());
    }

    public function test_to_array_returns_snake_case_keys(): void
    {
        $dto   = CrossPlatformInsightData::fromArray([
            'title'              => 'Test',
            'narrative'          => 'Narrative',
            'platforms_involved' => ['dot.crm'],
            'insight_type'       => 'risk',
            'confidence'         => 0.75,
            'severity'           => 'warning',
        ]);
        $array = $dto->toArray();

        $this->assertArrayHasKey('platforms_involved', $array);
        $this->assertArrayHasKey('insight_type', $array);
        $this->assertEquals('risk', $array['insight_type']);
    }
}
