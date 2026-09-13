<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Collector;

use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\Http\DomXPathPriceExtractor;
use KK\PriceWatch\Collector\Http\HttpFetchResult;
use KK\PriceWatch\Collector\Http\HttpHtmlCollector;
use KK\PriceWatch\Collector\Http\HttpPriceExtractionOptions;
use KK\PriceWatch\Collector\Http\HttpTransportInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpHtmlCollectorTest extends TestCase
{
    public function testBatchPreservesIdsAndMixedResults(): void
    {
        $transport = new FakeHttpTransport([
            'https://example.test/a?q=1&b=2' => HttpFetchResult::success(200, 'text/html; charset=utf-8', '<b>10</b>', 'https://example.test/a?q=1&b=2'),
            'https://example.test/b' => HttpFetchResult::failure(),
            'https://example.test/c' => HttpFetchResult::success(200, 'application/xhtml+xml', '<b>30.50</b>', 'https://example.test/c'),
        ]);
        $response = $this->collector($transport)->collect(new CollectorRequest('1.0', 'request-7', [
            new CollectorItem('a', 'https://example.test/a?q=1&b=2'),
            new CollectorItem('b', 'https://example.test/b'),
            new CollectorItem('c', 'https://example.test/c'),
        ]));
        self::assertSame('request-7', $response->requestId);
        self::assertSame(['a', 'b', 'c'], array_column($response->toArray()['items'], 'id'));
        self::assertSame('10.00', $response->items[0]->price);
        self::assertSame('HTTP_REQUEST_FAILED', $response->items[1]->error?->code);
        self::assertSame('30.50', $response->items[2]->price);
        self::assertSame(['https://example.test/a?q=1&b=2', 'https://example.test/b', 'https://example.test/c'], $transport->urls);
    }

    #[DataProvider('statusCodes')]
    public function testHttpStatusesAreItemFailures(int $status): void
    {
        $transport = new FakeHttpTransport(['https://example.test/a' => HttpFetchResult::success($status, 'text/html', '<b>10</b>', '')]);
        $result = $this->one($transport, 'https://example.test/a');
        self::assertSame('HTTP_STATUS', $result->error?->code);
    }

    public static function statusCodes(): array { return [[404], [403], [429], [500], [302]]; }

    #[DataProvider('disallowedUrls')]
    public function testUnsafeUrlsAreRejectedBeforeTransport(string $url): void
    {
        $transport = new FakeHttpTransport([]);
        self::assertSame('URL_NOT_ALLOWED', $this->one($transport, $url)->error?->code);
        self::assertSame([], $transport->urls);
    }

    public static function disallowedUrls(): array
    {
        return [
            ['https://example.test.attacker.invalid/a'], ['file://example.test/a'],
            ['https://user:password@example.test/a'], ['https://127.0.0.1/a'], ['relative/path'],
        ];
    }

    public function testNonHtmlIsRejected(): void
    {
        $transport = new FakeHttpTransport(['https://example.test/a' => HttpFetchResult::success(200, 'application/json', '{}', '')]);
        self::assertSame('INVALID_CONTENT_TYPE', $this->one($transport, 'https://example.test/a')->error?->code);
    }

    private function one(FakeHttpTransport $transport, string $url): object
    {
        return $this->collector($transport)->collect(new CollectorRequest('1.0', 'r', [new CollectorItem('1', $url)]))->items[0];
    }

    private function collector(FakeHttpTransport $transport): HttpHtmlCollector
    {
        return new HttpHtmlCollector('example.test', new HttpPriceExtractionOptions('//b', 'RUB'), $transport, new DomXPathPriceExtractor());
    }
}

final class FakeHttpTransport implements HttpTransportInterface
{
    /** @var list<string> */ public array $urls = [];
    /** @param array<string, HttpFetchResult> $results */ public function __construct(private array $results) {}
    public function fetch(string $url): HttpFetchResult { $this->urls[] = $url; return $this->results[$url] ?? HttpFetchResult::failure(); }
}
