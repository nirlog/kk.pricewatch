<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Collector;

use KK\PriceWatch\Collector\Http\DomXPathPriceExtractor;
use KK\PriceWatch\Collector\Http\HtmlPriceExtractionException;
use KK\PriceWatch\Collector\Http\HttpPriceExtractionOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomXPathPriceExtractorTest extends TestCase
{
    #[DataProvider('prices')]
    public function testRussianPriceWhitespaceIsNormalized(string $text, string $expected): void
    {
        $html = '<html><body><span class="price">' . $text . '</span></body></html>';
        self::assertSame($expected, (new DomXPathPriceExtractor())->extract($html, $this->options()));
    }

    public static function prices(): array
    {
        return [
            ['187 040 ₽', '187040.00'],
            ["187\u{00A0}040 ₽", '187040.00'],
            ["187\u{202F}040 ₽", '187040.00'],
            ['187040.00', '187040.00'],
            ['999999999999999999999999.01', '999999999999999999999999.01'],
        ];
    }

    public function testMissingAmbiguousAndInvalidValuesHaveStableErrors(): void
    {
        $extractor = new DomXPathPriceExtractor();
        foreach ([
            ['<b>1</b>', 'PRICE_NOT_FOUND'],
            ['<span class="price">10</span><span class="price">20</span>', 'PRICE_AMBIGUOUS'],
            ['<span class="price">not a price</span>', 'PRICE_INVALID'],
        ] as [$html, $code]) {
            try {
                $extractor->extract($html, $this->options());
                self::fail('Extraction should fail.');
            } catch (HtmlPriceExtractionException $exception) {
                self::assertSame($code, $exception->errorCode);
            }
        }
    }

    private function options(): HttpPriceExtractionOptions
    {
        return new HttpPriceExtractionOptions('//span[@class="price"]', 'RUB');
    }
}
