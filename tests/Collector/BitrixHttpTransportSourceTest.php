<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Collector;

use PHPUnit\Framework\TestCase;

final class BitrixHttpTransportSourceTest extends TestCase
{
    public function testProductionTransportHasConservativeBitrixClientPolicy(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Collector/Http/BitrixHttpTransport.php');
        self::assertStringContainsString('new HttpClient([', $source);
        self::assertStringContainsString("'socketTimeout' => self::SOCKET_TIMEOUT", $source);
        self::assertStringContainsString("'streamTimeout' => self::STREAM_TIMEOUT", $source);
        self::assertStringContainsString("'redirect' => false", $source);
        self::assertStringContainsString("'redirectMax' => 0", $source);
        self::assertStringContainsString('setPrivateIp(false)', $source);
        self::assertStringContainsString('setBodyLengthMax(self::BODY_LIMIT)', $source);
        self::assertStringNotContainsString('setBodyLength(self::BODY_LIMIT)', $source);
        self::assertStringContainsString('$client->get($url)', $source);
        self::assertStringNotContainsString('disableSslVerification', $source);
        self::assertDoesNotMatchRegularExpression('/curl_|file_get_contents|exec\s*\(|setPrivateIp\(true\)|post\s*\(/i', $source);
    }

    public function testCollectorDoesNotContainPersistenceOrScheduling(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Collector/Http/HttpHtmlCollector.php');
        self::assertDoesNotMatchRegularExpression('/Table::|DataManager|Scheduled|LAST_SUCCESS|CURRENT_PRICE/', $source);
    }
}
