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
    public function testSettingsDebugDumpDoesNotExposeToken(): void { $settings=new ExternalCollectorSettings(true,'https://collector.test','top-secret',5,60); ob_start(); var_dump($settings); $dump=(string)ob_get_clean(); self::assertStringNotContainsString('top-secret',$dump); self::assertStringContainsString('tokenConfigured',$dump); self::assertStringContainsString('bool(true)',$dump); }
    public function testCollectorDebugDumpDoesNotExposeToken(): void { $transport=new class implements ExternalCollectorTransportInterface { public function post(string $endpoint,string $body,array $headers,int $connectTimeout,int $requestTimeout): ExternalCollectorHttpResult { return ExternalCollectorHttpResult::failure(); } }; $collector=new ExternalCollector('https://collector.test/x','top-secret',5,60,$transport,new ExternalCollectorResponseDecoder()); ob_start(); var_dump($collector); $dump=(string)ob_get_clean(); self::assertStringNotContainsString('top-secret',$dump); self::assertStringContainsString('tokenConfigured',$dump); self::assertStringContainsString('bool(true)',$dump); }
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
    public function testEmptyOptionsAreEncodedAsJsonObject(): void
    {
        $fake = new class implements ExternalCollectorTransportInterface {
            public string $body = '';
            public function post(string $endpoint, string $body, array $headers, int $connectTimeout, int $requestTimeout): ExternalCollectorHttpResult
            {
                $this->body = $body;
                return ExternalCollectorHttpResult::response(200, 'application/json', '{"schema_version":"1.0","request_id":"r","success":true,"items":[]}');
            }
        };
        $collector = new ExternalCollector('https://collector.test/x', 'top-secret', 5, 60, $fake, new ExternalCollectorResponseDecoder());
        $collector->collect(new CollectorRequest('1.0', 'r', [new CollectorItem('1', 'https://shop.test/p')]));
        $payload = json_decode($fake->body, false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(\stdClass::class, $payload->options);
        self::assertSame([], get_object_vars($payload->options));
        self::assertStringContainsString('"options":{}', $fake->body);
    }
    public function testRemoteGlobalErrorMessageRedactsToken(): void
    {
        $response = $this->collectorReturning('{"schema_version":"1.0","request_id":"r","success":false,"items":[],"error":{"code":"COLLECTOR_ERROR","message":"Token top-secret was rejected"}}')
            ->collect(new CollectorRequest('1.0', 'r', [new CollectorItem('1', 'https://shop.test/p')]));
        self::assertSame('COLLECTOR_ERROR', $response->error?->code);
        self::assertSame('Token [REDACTED] was rejected', $response->error?->message);
    }
    public function testRemoteItemErrorMessageRedactsToken(): void
    {
        $response = $this->collectorReturning('{"schema_version":"1.0","request_id":"r","success":true,"items":[{"id":"1","success":false,"error":{"code":"PRICE_NOT_FOUND","message":"top-secret could not collect"}}]}')
            ->collect(new CollectorRequest('1.0', 'r', [new CollectorItem('1', 'https://shop.test/p')]));
        self::assertSame('PRICE_NOT_FOUND', $response->items[0]->error?->code);
        self::assertSame('[REDACTED] could not collect', $response->items[0]->error?->message);
    }
    public function testRemoteErrorWithoutTokenIsUnchanged(): void
    {
        $response = $this->collectorReturning('{"schema_version":"1.0","request_id":"r","success":false,"items":[],"error":{"code":"COLLECTOR_ERROR","message":"Ordinary remote failure"}}')
            ->collect(new CollectorRequest('1.0', 'r', [new CollectorItem('1', 'https://shop.test/p')]));
        self::assertSame('Ordinary remote failure', $response->error?->message);
    }
    #[DataProvider('failures')]
    public function testFailureMapping(ExternalCollectorHttpResult $result,string $code): void { $fake=new class($result) implements ExternalCollectorTransportInterface { public function __construct(private ExternalCollectorHttpResult $r){} public function post(string $e,string $b,array $h,int $c,int $r): ExternalCollectorHttpResult{return $this->r;} }; $response=(new ExternalCollector('https://x.test/x','secret',5,60,$fake,new ExternalCollectorResponseDecoder()))->collect(new CollectorRequest('1.0','r',[new CollectorItem('1','https://p.test')])); self::assertSame($code,$response->error?->code); self::assertStringNotContainsString('secret',$response->error?->message??''); }
    public static function failures(): array { return [[ExternalCollectorHttpResult::failure('timeout'),'COLLECTOR_TIMEOUT'],[ExternalCollectorHttpResult::failure('network'),'COLLECTOR_ERROR'],[ExternalCollectorHttpResult::failure('oversized'),'INVALID_RESPONSE'],[ExternalCollectorHttpResult::response(500,'application/json','secret'),'COLLECTOR_ERROR'],[ExternalCollectorHttpResult::response(200,'text/html','{}'),'INVALID_RESPONSE'],[ExternalCollectorHttpResult::response(200,'application/json','{bad'),'INVALID_RESPONSE']]; }

    private function collectorReturning(string $body): ExternalCollector
    {
        $transport = new class($body) implements ExternalCollectorTransportInterface {
            public function __construct(private readonly string $body) {}
            public function post(string $endpoint, string $body, array $headers, int $connectTimeout, int $requestTimeout): ExternalCollectorHttpResult
            {
                return ExternalCollectorHttpResult::response(200, 'application/json', $this->body);
            }
        };
        return new ExternalCollector('https://collector.test/x', 'top-secret', 5, 60, $transport, new ExternalCollectorResponseDecoder());
    }
}
