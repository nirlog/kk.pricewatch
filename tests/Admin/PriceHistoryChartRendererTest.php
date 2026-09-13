<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use KK\PriceWatch\Admin\PriceHistoryChartRenderer;
use PHPUnit\Framework\TestCase;

final class PriceHistoryChartRendererTest extends TestCase
{
    public function testOneAndEqualPricePointsRenderDeterministicallyAndEscapeText(): void
    {
        $points = [
            ['id' => 1, 'price' => '10.00', 'currency' => 'RUB', 'collected_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'price' => '10.00', 'currency' => 'RUB', 'collected_at' => '2026-01-01 00:00:00'],
        ];
        $svg = PriceHistoryChartRenderer::render($points, '<Title>', 'A & B');
        self::assertStringContainsString('<polyline', $svg);
        self::assertSame(2, substr_count($svg, '<circle'));
        self::assertStringContainsString('&lt;Title&gt;', $svg);
        self::assertStringContainsString('A &amp; B', $svg);
        self::assertSame($svg, PriceHistoryChartRenderer::render($points, '<Title>', 'A & B'));
        self::assertSame('', PriceHistoryChartRenderer::render([], 'x', 'y'));
    }
}
