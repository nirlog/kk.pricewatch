<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use PHPUnit\Framework\TestCase;

final class PriceUpdateServiceSourceTest extends TestCase
{
    private static function source(string $path = 'lib/Service/PriceUpdateService.php'): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    private static function method(string $name): string
    {
        $source = self::source();
        self::assertMatchesRegularExpression('/function\s+' . preg_quote($name, '/') . '\s*\(/', $source);
        preg_match('/function\s+' . preg_quote($name, '/') . '\s*\(/', $source, $match, PREG_OFFSET_CAPTURE);
        $start = (int) $match[0][1];
        $openingBrace = strpos($source, '{', $start);
        self::assertNotFalse($openingBrace);
        $depth = 0;
        for ($offset = $openingBrace, $length = strlen($source); $offset < $length; $offset++) {
            if ($source[$offset] === '{') {
                $depth++;
            } elseif ($source[$offset] === '}' && --$depth === 0) {
                return substr($source, $start, $offset - $start + 1);
            }
        }
        self::fail(sprintf('Could not find the end of method %s().', $name));
    }

    private static function compact(string $source): string
    {
        return (string) preg_replace('/\s+/', ' ', $source);
    }

    public function testUpdateLinksNormalizesAndLoadsOnlyTheExplicitFiniteBatch(): void
    {
        $method = self::compact(self::method('updateLinks'));
        self::assertStringContainsString('$ids = $this->normalizeIds($linkIds)', $method);
        self::assertMatchesRegularExpression('/ProductCompetitorTable::getList\s*\(\s*\[\s*[\'\"]filter[\'\"]\s*=>\s*\[\s*[\'\"]@ID[\'\"]\s*=>\s*\$ids/', $method);
        self::assertSame(1, substr_count($method, 'ProductCompetitorTable::getList('));

        $normalizer = self::compact(self::method('normalizeIds'));
        self::assertStringContainsString('if ($linkIds === [])', $normalizer);
        self::assertStringContainsString('!is_int($id) || $id <= 0', $normalizer);
        self::assertStringContainsString('$ids[$id] = $id', $normalizer);
    }

    public function testLinksAreGroupedAndCompetitorsAreBatchLoadedWithoutNPlusOne(): void
    {
        $method = self::compact(self::method('updateLinks'));
        self::assertStringContainsString('$competitorIds = array_values(array_unique(', $method);
        self::assertMatchesRegularExpression('/CompetitorTable::getList\s*\(\s*\[\s*[\'\"]filter[\'\"]\s*=>\s*\[\s*[\'\"]@ID[\'\"]\s*=>\s*\$competitorIds/', $method);
        self::assertSame(1, preg_match_all('/(?<![A-Za-z0-9_])CompetitorTable::getList\s*\(/', $method));
        self::assertStringContainsString('$groups[(int) $link[\'COMPETITOR_ID\']][] = $link', $method);
        self::assertMatchesRegularExpression('/foreach\s*\(\$groups\s+as\s+\$competitorId\s*=>\s*\$group\).*\$this->processGroup\(\$competitors\[\$competitorId\],\s*\$group\)/s', $method);
        self::assertSame(1, substr_count($method, '$this->processGroup('));
        self::assertDoesNotMatchRegularExpression('/DNS|KometaPC|RoyalPC/i', self::source());
    }

    public function testCollectorItemsUseLinkIdentityAndExactStoredUrl(): void
    {
        $method = self::compact(self::method('processGroup'));
        self::assertMatchesRegularExpression('/new CollectorItem\(\(string\)\s*\$link\[[\'\"]ID[\'\"]\],\s*\(string\)\s*\$link\[[\'\"]URL[\'\"]\]\)/', $method);
        self::assertDoesNotMatchRegularExpression('/trim\s*\([^)]*\$link\[[\'\"]URL[\'\"]\]/', $method);
        self::assertDoesNotMatchRegularExpression('/parse_url|http_build_query/i', self::source());
    }

    public function testCollectorBoundaryAndNoNetworkPolicyRemainIsolatedInFactory(): void
    {
        $service = self::source();
        self::assertStringContainsString('private readonly CollectorFactoryInterface $collectorFactory', $service);
        self::assertStringNotContainsString('MockCollector', $service);
        self::assertDoesNotMatchRegularExpression('/curl_|file_get_contents\s*\(|HttpClient|Socket|stream_socket|fsockopen/i', $service);

        $factory = self::source('lib/Service/DefaultCollectorFactory.php');
        self::assertStringContainsString('implements CollectorFactoryInterface', $factory);
        self::assertStringContainsString('return new MockCollector(', $factory);
        self::assertStringContainsString("!== CollectorType::MOCK", $factory);
        self::assertStringContainsString('configured collector type is not available', $factory);
    }

    public function testResponseCorrelationCompletesBeforeAnyClaimedSuccessIsPersisted(): void
    {
        $correlation = self::compact(self::method('isCorrelated'));
        self::assertStringContainsString('$response->requestId !== $request->requestId', $correlation);
        self::assertStringContainsString('if (!$response->success) { return true; }', $correlation);
        self::assertStringContainsString('if (!isset($requested[$item->id])) { return false; }', $correlation);
        self::assertStringContainsString('unset($requested[$item->id])', $correlation);
        self::assertStringContainsString('return $requested === []', $correlation);

        $group = self::compact(self::method('processGroup'));
        $validation = strpos($group, 'if (!$this->isCorrelated($request, $response))');
        $persistence = strpos($group, '$this->persistItem(');
        self::assertNotFalse($validation);
        self::assertNotFalse($persistence);
        self::assertLessThan($persistence, $validation);
        self::assertStringContainsString('persistGroupError($links, \'INVALID_RESPONSE\'', $group);
    }

    public function testSuccessPersistsTheCompleteDecimalStringState(): void
    {
        $method = self::compact(self::method('persistItem'));
        foreach (['CURRENT_PRICE', 'CURRENCY', 'STATUS', 'ERROR_CODE', 'ERROR_MESSAGE', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT'] as $field) {
            self::assertStringContainsString("'$field' =>", $method);
        }
        self::assertStringContainsString("'ERROR_CODE' => null", $method);
        self::assertStringContainsString("'ERROR_MESSAGE' => null", $method);
        self::assertStringContainsString("'CURRENT_PRICE' => \$item->price", $method);
        self::assertDoesNotMatchRegularExpression('/\(float\).*\$item->price|floatval\s*\(.*\$item->price/', $method);
    }

    public function testErrorsPreserveStaleSuccessfulState(): void
    {
        $method = self::compact(self::method('persistError'));
        foreach (['STATUS', 'ERROR_CODE', 'ERROR_MESSAGE', 'LAST_CHECK_AT'] as $field) {
            self::assertStringContainsString("'$field' =>", $method);
        }
        foreach (['CURRENT_PRICE', 'CURRENCY', 'LAST_SUCCESS_AT'] as $field) {
            self::assertStringNotContainsString("'$field' =>", $method);
        }
    }

    public function testPersistenceFailuresAreIsolatedPerLinkWithoutBatchTransaction(): void
    {
        $update = self::compact(self::method('update'));
        self::assertStringContainsString('ProductCompetitorTable::update($id, $fields)', $update);
        self::assertStringContainsString('PriceUpdateOutcome::PERSISTENCE_FAILURE', $update);
        self::assertStringContainsString("'PERSISTENCE_ERROR'", $update);
        self::assertDoesNotMatchRegularExpression('/startTransaction|commitTransaction|rollbackTransaction|Transaction/', self::source());
        self::assertStringContainsString('array_map(', self::method('persistGroupError'));
        self::assertStringContainsString('array_map(', self::method('processGroup'));
    }

    public function testMissingAndInactiveRowsProduceMachineReadablePreGroupOutcomes(): void
    {
        $method = self::compact(self::method('updateLinks'));
        foreach (['NOT_FOUND', 'INACTIVE_LINK', 'INACTIVE_COMPETITOR', 'COLLECTOR_ERROR'] as $code) {
            self::assertStringContainsString("'$code'", $method);
        }
        self::assertLessThan(strpos($method, '$groups['), strpos($method, "'INACTIVE_LINK'"));
        self::assertLessThan(strpos($method, '$groups['), strpos($method, "'INACTIVE_COMPETITOR'"));
        self::assertStringContainsString('$competitor === null', $method);
        self::assertStringContainsString('self::GENERIC_ERROR', $method);
    }

    public function testFactoryAndCollectorExceptionsBecomeSafePerGroupErrors(): void
    {
        $method = self::compact(self::method('processGroup'));
        self::assertMatchesRegularExpression('/try\s*\{.*collectorFactory->create\(\$competitor\)->collect\(\$request\).*\}\s*catch\s*\(Throwable\)/s', $method);
        self::assertStringContainsString('persistGroupError($links, \'COLLECTOR_ERROR\', self::GENERIC_ERROR)', $method);
        self::assertStringNotContainsString('getMessage()', $method);
        self::assertMatchesRegularExpression('/foreach\s*\(\$groups.*processGroup/s', self::method('updateLinks'));
    }
}
