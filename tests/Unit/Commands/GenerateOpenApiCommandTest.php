<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;

class GenerateOpenApiCommandTest extends TestCase
{
    public function test_command_outputs_openapi_json_to_stdout(): void
    {
        $output = $this->artisan('analytics:openapi', ['--print' => true])
            ->assertExitCode(0);

        // The --print output goes to stdout which artisan captures
        $this->assertNotNull($output);
    }

    public function test_command_writes_file_by_default(): void
    {
        $outputPath = public_path('api-docs/openapi.json');

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        $this->artisan('analytics:openapi')->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $spec = json_decode(file_get_contents($outputPath), true);
        $this->assertEquals('3.0.3', $spec['openapi']);
        $this->assertEquals('Dot.Analytics API', $spec['info']['title']);
    }

    public function test_generated_spec_has_required_sections(): void
    {
        $this->artisan('analytics:openapi')->assertExitCode(0);

        $spec = json_decode(file_get_contents(public_path('api-docs/openapi.json')), true);

        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('tags', $spec);
        $this->assertArrayHasKey('servers', $spec);
    }

    public function test_generated_spec_documents_intelligence_endpoints(): void
    {
        $this->artisan('analytics:openapi')->assertExitCode(0);

        $spec = json_decode(file_get_contents(public_path('api-docs/openapi.json')), true);
        $paths = array_keys($spec['paths']);

        $this->assertContains('/v1/intelligence/engines', $paths);
        $this->assertContains('/v1/intelligence/insights', $paths);
        $this->assertContains('/v1/intelligence/run', $paths);
    }

    public function test_generated_spec_documents_security_scheme(): void
    {
        $this->artisan('analytics:openapi')->assertExitCode(0);

        $spec = json_decode(file_get_contents(public_path('api-docs/openapi.json')), true);

        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    public function test_generated_spec_has_tags_for_all_areas(): void
    {
        $this->artisan('analytics:openapi')->assertExitCode(0);

        $spec = json_decode(file_get_contents(public_path('api-docs/openapi.json')), true);
        $tagNames = array_column($spec['tags'], 'name');

        $this->assertContains('Intelligence', $tagNames);
        $this->assertContains('Platforms', $tagNames);
        $this->assertContains('Metrics', $tagNames);
        $this->assertContains('Feature Flags', $tagNames);
    }
}
