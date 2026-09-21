<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Collector\External;
use PHPUnit\Framework\TestCase;
final class BitrixExternalCollectorTransportSourceTest extends TestCase
{
    public function testTransportHardeningIsExplicit(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/lib/Collector/External/BitrixExternalCollectorTransport.php');
        self::assertStringContainsString("'redirect' => false", $source);
        self::assertStringContainsString("'redirectMax' => 0", $source);
        self::assertStringContainsString('setBodyLengthMax(self::BODY_LIMIT)', $source);
        self::assertStringContainsString("'socketTimeout' => \$connectTimeout", $source);
        self::assertStringContainsString("'streamTimeout' => \$requestTimeout", $source);
        self::assertStringNotContainsString('Authorization', $source);
    }
}
