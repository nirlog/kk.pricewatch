<?php

declare(strict_types=1);

namespace KK\PriceWatch\Admin;

final class PriceHistoryChartRenderer
{
    /** @param list<array{id:int, price:string, currency:string, collected_at:string}> $points */
    public static function render(array $points, string $title, string $description): string
    {
        if ($points === []) return '';
        $width = 800; $height = 220; $padding = 24;
        $cents = array_map(self::cents(...), array_column($points, 'price'));
        $min = min($cents); $max = max($cents); $range = max(1, $max - $min);
        $last = count($points) - 1;
        $coordinates = [];
        foreach ($points as $index => $point) {
            $x = $last === 0 ? intdiv($width, 2) : $padding + (int) round($index * ($width - 2 * $padding) / $last);
            $y = $height - $padding - (int) round(($cents[$index] - $min) * ($height - 2 * $padding) / $range);
            $coordinates[] = $x . ',' . $y;
        }
        $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $dots = '';
        foreach ($coordinates as $index => $coordinate) {
            $point = $points[$index];
            $dots .= '<circle cx="' . str_replace(',', '" cy="', $coordinate) . '" r="4"><title>'
                . $escape($point['collected_at'] . ': ' . $point['price'] . ' ' . $point['currency']) . '</title></circle>';
        }
        return '<svg class="kk-pricewatch-chart" viewBox="0 0 800 220" role="img" aria-labelledby="kk-pw-chart-title kk-pw-chart-desc" style="width:100%;height:auto;max-height:260px">'
            . '<title id="kk-pw-chart-title">' . $escape($title) . '</title><desc id="kk-pw-chart-desc">' . $escape($description) . '</desc>'
            . '<polyline fill="none" stroke="#2067b0" stroke-width="3" points="' . implode(' ', $coordinates) . '"/>' . $dots . '</svg>';
    }

    private static function cents(string $price): int
    {
        [$integer, $fraction] = explode('.', $price);
        return (int) ($integer . $fraction); // Coordinate scaling only; never used by business decisions.
    }
}
