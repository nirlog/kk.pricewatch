<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use KK\PriceWatch\Admin\PriceHistoryChartRenderer;
use PHPUnit\Framework\TestCase;

final class PriceHistoryChartRendererTest extends TestCase
{
    public function testExactlyOnePointRendersSafelyAndDeterministically(): void
    {
        $points = [[
            'id' => 7,
            'price' => '129990.00',
            'currency' => 'RUB',
            'collected_at' => '2026-09-13 12:34:56',
        ]];

        $svg = PriceHistoryChartRenderer::render($points, 'Price history', 'One point');

        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('<polyline', $svg);
        self::assertSame(1, substr_count($svg, '<circle'));
        self::assertStringContainsString('129990.00 RUB', $svg);
        self::assertStringContainsString('2026-09-13 12:34:56', $svg);
        self::assertStringNotContainsString('INF', $svg);
        self::assertStringNotContainsString('NAN', $svg);
        self::assertSame($svg, PriceHistoryChartRenderer::render($points, 'Price history', 'One point'));
    }

    public function testEqualPricePointsRenderDeterministicallyAndEscapeText(): void
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
