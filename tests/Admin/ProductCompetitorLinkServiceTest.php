<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use KK\PriceWatch\Admin\ProductCompetitorLinkService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductCompetitorLinkServiceTest extends TestCase
{
    #[DataProvider('acceptedUrls')]
    public function testAcceptsAbsoluteHttpUrlsWithoutChangingThem(string $url): void
    {
        self::assertTrue(ProductCompetitorLinkService::isAcceptedHttpUrl($url));
    }

    public static function acceptedUrls(): array
    {
        return [
            ['https://Example.test/item?b=2&a=%2F#choice'],
            ['http://example.test:8080/path'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function testRejectsUnsafeOrNonAbsoluteUrls(string $url): void
    {
        self::assertFalse(ProductCompetitorLinkService::isAcceptedHttpUrl($url));
    }

    public static function rejectedUrls(): array
    {
        return [[''], ['  '], ['javascript:alert(1)'], ['data:text/plain,x'], ['file:///tmp/x'], ['/relative']];
    }
}
