<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Collector\External;
use InvalidArgumentException;
use KK\PriceWatch\Collector\External\ExternalCollectorResponseDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class ExternalCollectorResponseDecoderTest extends TestCase
{
    public function testValidBatchMixedAndExtras(): void { $r=(new ExternalCollectorResponseDecoder())->decode('{"schema_version":"1.0","request_id":"r","success":true,"extra":1,"items":[{"id":"1","success":true,"price":"12.30","currency":"RUB","future":1},{"id":"2","success":false,"error":{"code":"PRICE_NOT_FOUND","message":"No"}}]}'); self::assertTrue($r->success); self::assertCount(2,$r->items); self::assertFalse($r->items[1]->success); }
    public function testValidGlobalFailure(): void { $r=(new ExternalCollectorResponseDecoder())->decode('{"schema_version":"1.0","request_id":"r","success":false,"items":[],"error":{"code":"COLLECTOR_ERROR","message":"Failed"}}'); self::assertSame('COLLECTOR_ERROR',$r->error?->code); }
    #[DataProvider('invalidResponses')]
    public function testRejectsMalformedContract(string $json): void { $this->expectException(InvalidArgumentException::class); (new ExternalCollectorResponseDecoder())->decode($json); }
    public static function invalidResponses(): array { return array_map(static fn($v)=>[$v],[
        '{bad','[]','{}','{"schema_version":"2.0","request_id":"r","success":true,"items":[]}',
        '{"schema_version":"1.0","request_id":"","success":true,"items":[]}',
        '{"schema_version":"1.0","request_id":"r","success":"yes","items":[]}',
        '{"schema_version":"1.0","request_id":"r","success":true,"items":{}}',
        '{"schema_version":"1.0","request_id":"r","success":true,"items":[{"id":"1","success":true,"price":"1","currency":"RUB"},{"id":"1","success":false,"error":{"code":"X","message":"x"}}]}',
        '{"schema_version":"1.0","request_id":"r","success":true,"items":[{"id":"1","success":true,"price":1,"currency":"RUB"}]}',
        '{"schema_version":"1.0","request_id":"r","success":true,"items":[{"id":"1","success":true,"price":"-1","currency":"RUB"}]}',
        '{"schema_version":"1.0","request_id":"r","success":true,"items":[{"id":"1","success":true,"price":"1","currency":"rub"}]}',
        '{"schema_version":"1.0","request_id":"r","success":true,"items":[{"id":"1","success":false,"error":{"code":"bad-code","message":"x"}}]}',
        '{"schema_version":"1.0","request_id":"r","success":false,"items":[{"id":"1","success":false,"error":{"code":"X","message":"x"}}],"error":{"code":"X","message":"x"}}'
    ]); }
}
