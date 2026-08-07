<?php

namespace Tests\Unit\Services;

use App\Services\CurrencyService;
use Tests\TestCase;

class CurrencyServiceTest extends TestCase
{
    private CurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CurrencyService;
    }

    public function test_format_usd_prepends_dollar_sign(): void
    {
        $this->assertEquals('$1,234.56', $this->service->format(1234.56, 'USD'));
    }

    public function test_format_zar_prepends_rand_symbol(): void
    {
        $this->assertEquals('R1,234.56', $this->service->format(1234.56, 'ZAR'));
    }

    public function test_format_jpy_has_no_decimals(): void
    {
        $this->assertEquals('¥1,235', $this->service->format(1234.56, 'JPY'));
    }

    public function test_format_compact_millions(): void
    {
        $result = $this->service->format(1_500_000, 'USD', compact: true);
        $this->assertStringContainsString('1.5M', $result);
    }

    public function test_format_compact_thousands(): void
    {
        $result = $this->service->format(2500, 'USD', compact: true);
        $this->assertStringContainsString('2.5K', $result);
    }

    public function test_format_compact_billions(): void
    {
        $result = $this->service->format(3_200_000_000, 'USD', compact: true);
        $this->assertStringContainsString('3.2B', $result);
    }

    public function test_symbol_returns_correct_symbol(): void
    {
        $this->assertEquals('R', $this->service->symbol('ZAR'));
        $this->assertEquals('$', $this->service->symbol('USD'));
        $this->assertEquals('£', $this->service->symbol('GBP'));
    }

    public function test_symbol_returns_code_for_unknown_currency(): void
    {
        $this->assertEquals('XYZ', $this->service->symbol('XYZ'));
    }

    public function test_supported_returns_array_of_currencies(): void
    {
        $supported = $this->service->supported();
        $this->assertNotEmpty($supported);
        $codes = array_column($supported, 'code');
        $this->assertContains('USD', $codes);
        $this->assertContains('ZAR', $codes);
        $this->assertContains('EUR', $codes);
    }

    public function test_convert_returns_same_amount_for_same_currency(): void
    {
        $this->assertEquals(100.0, $this->service->convert(100.0, 'USD', 'USD', 1.0));
    }

    public function test_convert_applies_rate(): void
    {
        $result = $this->service->convert(100.0, 'USD', 'ZAR', 18.5);
        $this->assertEquals(1850.0, $result);
    }

    public function test_format_defaults_to_usd_for_unknown_currency(): void
    {
        $result = $this->service->format(100.0, 'UNKNOWN');
        $this->assertStringContainsString('$', $result);
    }
}
