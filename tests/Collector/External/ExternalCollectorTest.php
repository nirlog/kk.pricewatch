<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Collector\External;
use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\External\{ExternalCollector,ExternalCollectorEndpoint,ExternalCollectorHttpResult,ExternalCollectorResponseDecoder,ExternalCollectorSettings,ExternalCollectorTransportInterface};
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class ExternalCollectorTest extends TestCase
{
    #[DataProvider('validEndpoints')]
    public function testEndpointResolution(string $base,string $handler,string $expected): void { self::assertSame($expected,ExternalCollectorEndpoint::resolve($base,$handler)); }
    public static function validEndpoints(): array { return [['https://collector.test','/api/v1/collect','https://collector.test/api/v1/collect'],['https://collector.test/prefix/','/api/collectors/browser','https://collector.test/prefix/api/collectors/browser'],['http://127.0.0.1:8000','/x','http://127.0.0.1:8000/x'],['http://[::1]:8000','/x','http://[::1]:8000/x']]; }
    #[DataProvider('invalidEndpointParts')]
    public function testRejectsUnsafeConfiguration(string $base,string $handler): void { $this->expectException(InvalidConfigurationException::class); ExternalCollectorEndpoint::resolve($base,$handler); }
    public static function invalidEndpointParts(): array { return [['http://example.test','/x'],['https://u:p@example.test','/x'],['https://example.test?q=1','/x'],['https://example.test#f','/x'],['https://example.test',''],['https://example.test','api/x'],['https://example.test','//evil/x'],['https://example.test','https://evil/x'],['https://example.test','/api/../x'],['https://example.test','/x?y=1'],['https://example.test','/x#f']]; }
    public function testSettingsValidationAndSecretSafeState(): void { $s=new ExternalCollectorSettings(true,'https://collector.test','top-secret',5,60); self::assertTrue($s->renderableState()['token_configured']); self::assertStringNotContainsString('top-secret',json_encode($s->renderableState(),JSON_THROW_ON_ERROR)); }
    public function testDisabledDefaultsAreValid(): void { $s=new ExternalCollectorSettings(false,'',''); self::assertFalse($s->enabled); }
    #[DataProvider('invalidSettings')]
    public function testInvalidSettings(array $args): void { $this->expectException(InvalidConfigurationException::class); new ExternalCollectorSettings(...$args); }
    public static function invalidSettings(): array { return [[ [true,'https://x.test','',5,60] ],[ [true,'','x',5,60] ],[ [true,'https://x.test','x',0,60] ],[ [true,'https://x.test','x',5,301] ],[ [true,'https://x.test','x',20,10] ]]; }
    public function testRequestAndResponse(): void {
        $fake=new class implements ExternalCollectorTransportInterface { public array $call=[]; public function post(string $endpoint,string $body,array $headers,int $connectTimeout,int $requestTimeout): ExternalCollectorHttpResult { $this->call=func_get_args(); return ExternalCollectorHttpResult::response(200,'application/json; charset=utf-8','{"schema_version":"1.0","request_id":"r","success":true,"items":[{"id":"12","success":true,"price":"10.00","currency":"RUB"}]}'); }};
        $collector=new ExternalCollector('https://collector.test/x','top-secret',5,60,$fake,new ExternalCollectorResponseDecoder());
        $response=$collector->collect(new CollectorRequest('1.0','r',[new CollectorItem('12','https://shop.test/p?a=1')],['region'=>'СПб']));
        self::assertTrue($response->success); self::assertSame('Bearer top-secret',$fake->call[2]['Authorization']); self::assertSame((new CollectorRequest('1.0','r',[new CollectorItem('12','https://shop.test/p?a=1')],['region'=>'СПб']))->toArray(),json_decode($fake->call[1],true)); self::assertStringNotContainsString('top-secret',$fake->call[1]);
    }
    #[DataProvider('failures')]
    public function testFailureMapping(ExternalCollectorHttpResult $result,string $code): void { $fake=new class($result) implements ExternalCollectorTransportInterface { public function __construct(private ExternalCollectorHttpResult $r){} public function post(string $e,string $b,array $h,int $c,int $r): ExternalCollectorHttpResult{return $this->r;} }; $response=(new ExternalCollector('https://x.test/x','secret',5,60,$fake,new ExternalCollectorResponseDecoder()))->collect(new CollectorRequest('1.0','r',[new CollectorItem('1','https://p.test')])); self::assertSame($code,$response->error?->code); self::assertStringNotContainsString('secret',$response->error?->message??''); }
    public static function failures(): array { return [[ExternalCollectorHttpResult::failure('timeout'),'COLLECTOR_TIMEOUT'],[ExternalCollectorHttpResult::failure('network'),'COLLECTOR_ERROR'],[ExternalCollectorHttpResult::failure('oversized'),'INVALID_RESPONSE'],[ExternalCollectorHttpResult::response(500,'application/json','secret'),'COLLECTOR_ERROR'],[ExternalCollectorHttpResult::response(200,'text/html','{}'),'INVALID_RESPONSE'],[ExternalCollectorHttpResult::response(200,'application/json','{bad'),'INVALID_RESPONSE']]; }
}
