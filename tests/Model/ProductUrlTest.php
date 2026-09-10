<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use InvalidArgumentException;
use KK\PriceWatch\Model\ProductUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductUrlTest extends TestCase
{
    public function testHashesExactUrlWithoutCanonicalization(): void
    {
        $url = 'https://example.test/product?b=2&a=%D1%82%D0%B5%D1%81%D1%82';
        self::assertSame(hash('sha256', $url), ProductUrl::hash($url));
        self::assertSame(ProductUrl::hash($url), ProductUrl::hash($url));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', ProductUrl::hash($url));
    }

    public function testQueryParameterOrderChangesHash(): void
    {
        self::assertNotSame(
            ProductUrl::hash('https://example.test/product?a=1&b=2'),
            ProductUrl::hash('https://example.test/product?b=2&a=1'),
        );
    }

    public function testUnicodeAndEncodedTextAreHashedByteForByte(): void
    {
        $unicode = 'https://example.test/товар?q=%D1%82%D0%B5%D1%81%D1%82';
        self::assertSame(hash('sha256', $unicode), ProductUrl::hash($unicode));
    }

    #[DataProvider('blankUrls')]
    public function testBlankUrlIsRejected(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProductUrl::hash($url);
    }

    public static function blankUrls(): array
    {
        return [[''], ['   '], ["\t\n"]];
    }
}
