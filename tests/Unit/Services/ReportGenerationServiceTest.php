<?php

namespace Tests\Unit\Services;

use App\Models\CrossPlatformInsight;
use App\Models\User;
use App\Services\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportGenerationService;
    }

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    public function test_generate_json_returns_required_structure(): void
    {
        $team = $this->team();
        $report = $this->service->generateJson($team, 'insights');

        $this->assertArrayHasKey('report_type', $report);
        $this->assertArrayHasKey('team', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('columns', $report);
        $this->assertArrayHasKey('rows', $report);
        $this->assertArrayHasKey('row_count', $report);
        $this->assertEquals('insights', $report['report_type']);
    }

    public function test_generate_json_insights_contains_data(): void
    {
        $team = $this->team();
        CrossPlatformInsight::factory()->count(3)->create(['team_id' => $team->id]);

        $report = $this->service->generateJson($team, 'insights');

        $this->assertEquals(3, $report['row_count']);
        $this->assertCount(3, $report['rows']);
    }

    public function test_generate_json_is_team_scoped(): void
    {
        $teamA = $this->team();
        $teamB = $this->team();

        CrossPlatformInsight::factory()->count(5)->create(['team_id' => $teamA->id]);

        $report = $this->service->generateJson($teamB, 'insights');

        $this->assertEquals(0, $report['row_count']);
    }

    public function test_generate_json_alerts_has_correct_columns(): void
    {
        $team = $this->team();
        $report = $this->service->generateJson($team, 'alerts');

        $this->assertContains('Title', $report['columns']);
        $this->assertContains('Severity', $report['columns']);
        $this->assertContains('Status', $report['columns']);
    }

    public function test_generate_json_recommendations_has_correct_columns(): void
    {
        $team = $this->team();
        $report = $this->service->generateJson($team, 'recommendations');

        $this->assertContains('Title', $report['columns']);
        $this->assertContains('Priority', $report['columns']);
        $this->assertContains('Engine', $report['columns']);
    }

    public function test_generate_html_returns_valid_html(): void
    {
        $team = $this->team();
        $html = $this->service->generateHtml($team, 'insights');

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString($team->name, $html);
    }

    public function test_generate_html_escapes_dangerous_content(): void
    {
        $team = $this->team();

        CrossPlatformInsight::factory()->create([
            'team_id' => $team->id,
            'title' => '<script>alert("xss")</script>',
        ]);

        $html = $this->service->generateHtml($team, 'insights');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_stream_csv_returns_streamed_response(): void
    {
        $team = $this->team();
        $response = $this->service->streamCsv($team, 'insights');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
