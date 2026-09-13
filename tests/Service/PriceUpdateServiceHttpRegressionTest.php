<?php

declare(strict_types=1);

namespace Bitrix\Main\Type {
    final class DateTime
    {
    }
}

namespace Bitrix\Main\ORM\Data {
    final class TestQueryResult
    {
        /** @param list<array<string, mixed>> $rows */
        public function __construct(private array $rows)
        {
        }

        /** @return array<string, mixed>|false */
        public function fetch(): array|false
        {
            return array_shift($this->rows) ?? false;
        }
    }

    final class TestUpdateResult
    {
        public function isSuccess(): bool
        {
            return true;
        }
    }

    class DataManager
    {
        /** @var array<int, array<string, mixed>> */
        public static array $links = [];
        /** @var array<int, array<string, mixed>> */
        public static array $competitors = [];

        /** @param array<string, mixed> $parameters */
        public static function getList(array $parameters): TestQueryResult
        {
            $rows = str_ends_with(static::class, 'ProductCompetitorTable') ? self::$links : self::$competitors;
            $ids = $parameters['filter']['@ID'] ?? [];
            return new TestQueryResult(array_values(array_intersect_key($rows, array_flip($ids))));
        }

        /** @param array<string, mixed> $fields */
        public static function update(int $id, array $fields): TestUpdateResult
        {
            self::$links[$id] = array_replace(self::$links[$id], $fields);
            return new TestUpdateResult();
        }
    }
}

namespace KK\PriceWatch\Tests\Service {
    use Bitrix\Main\ORM\Data\DataManager;
    use KK\PriceWatch\Collector\CollectorInterface;
    use KK\PriceWatch\Collector\Http\DomXPathPriceExtractor;
    use KK\PriceWatch\Collector\Http\HttpFetchResult;
    use KK\PriceWatch\Collector\Http\HttpHtmlCollector;
    use KK\PriceWatch\Collector\Http\HttpPriceExtractionOptions;
    use KK\PriceWatch\Collector\Http\HttpTransportInterface;
    use KK\PriceWatch\Model\CollectionStatus;
    use KK\PriceWatch\Model\CollectorType;
    use KK\PriceWatch\Service\CollectorFactoryInterface;
    use KK\PriceWatch\Service\CollectedLinkIdentity;
    use KK\PriceWatch\Service\PriceUpdateService;
    use KK\PriceWatch\Service\RequestIdGeneratorInterface;
    use KK\PriceWatch\Service\SuccessPersistenceInterface;
    use PHPUnit\Framework\TestCase;

    final class PriceUpdateServiceHttpRegressionTest extends TestCase
    {
        protected function setUp(): void
        {
            DataManager::$links = [7 => [
                'ID' => 7,
                'PRODUCT_ID' => 11,
                'COMPETITOR_ID' => 3,
                'URL' => 'https://example.test/product?region=msk',
                'URL_HASH' => hash('sha256', 'https://example.test/product?region=msk'),
                'ACTIVE' => 'Y',
                'CURRENT_PRICE' => '100.00',
                'CURRENCY' => 'RUB',
                'STATUS' => CollectionStatus::SUCCESS,
                'ERROR_CODE' => null,
                'ERROR_MESSAGE' => null,
                'LAST_SUCCESS_AT' => 'previous-success',
            ]];
            DataManager::$competitors = [3 => [
                'ID' => 3,
                'ACTIVE' => 'Y',
                'DOMAIN' => 'example.test',
                'COLLECTOR_TYPE' => CollectorType::HTTP,
                'COLLECTOR_HANDLER' => '',
                'COLLECTOR_OPTIONS' => '{"price_selector":{"type":"xpath","value":"//b"},"currency":"RUB"}',
            ]];
        }

        public function testHttpSuccessUsesExistingSuccessPersistencePath(): void
        {
            $result = $this->service(HttpFetchResult::success(
                200,
                'text/html',
                '<b>187 040 ₽</b>',
                DataManager::$links[7]['URL'],
            ))->updateLinks([7]);

            self::assertSame(1, $result->successCount());
            self::assertSame('187040.00', DataManager::$links[7]['CURRENT_PRICE']);
            self::assertSame('RUB', DataManager::$links[7]['CURRENCY']);
            self::assertSame(CollectionStatus::SUCCESS, DataManager::$links[7]['STATUS']);
            self::assertNull(DataManager::$links[7]['ERROR_CODE']);
            self::assertNull(DataManager::$links[7]['ERROR_MESSAGE']);
            self::assertInstanceOf(\Bitrix\Main\Type\DateTime::class, DataManager::$links[7]['LAST_CHECK_AT']);
            self::assertSame(DataManager::$links[7]['LAST_CHECK_AT'], DataManager::$links[7]['LAST_SUCCESS_AT']);
        }

        public function testHttpItemFailurePreservesStaleSuccessfulState(): void
        {
            $result = $this->service(HttpFetchResult::failure())->updateLinks([7]);

            self::assertSame(1, $result->errorCount());
            self::assertSame('100.00', DataManager::$links[7]['CURRENT_PRICE']);
            self::assertSame('RUB', DataManager::$links[7]['CURRENCY']);
            self::assertSame('previous-success', DataManager::$links[7]['LAST_SUCCESS_AT']);
            self::assertSame(CollectionStatus::ERROR, DataManager::$links[7]['STATUS']);
            self::assertSame('HTTP_REQUEST_FAILED', DataManager::$links[7]['ERROR_CODE']);
            self::assertInstanceOf(\Bitrix\Main\Type\DateTime::class, DataManager::$links[7]['LAST_CHECK_AT']);
        }

        public function testIdentityChangedDuringCollectionIsRejectedAsPersistenceFailure(): void
        {
            $persistence = new class implements SuccessPersistenceInterface {
                public function persist(
                    int $linkId,
                    CollectedLinkIdentity $collectedIdentity,
                    string $price,
                    string $currency,
                    \Bitrix\Main\Type\DateTime $collectedAt,
                ): bool {
                    return $collectedIdentity->matchesRow(DataManager::$links[$linkId]);
                }
            };
            $duringFetch = static function (): void {
                DataManager::$links[7]['URL'] = 'https://example.test/replacement';
                DataManager::$links[7]['URL_HASH'] = str_repeat('b', 64);
            };

            $result = $this->service(
                HttpFetchResult::success(200, 'text/html', '<b>187 040 ₽</b>', DataManager::$links[7]['URL']),
                $persistence,
                $duringFetch,
            )->updateLinks([7]);

            self::assertSame(1, $result->persistenceFailureCount());
            self::assertSame('100.00', DataManager::$links[7]['CURRENT_PRICE']);
        }

        public function testSuccessPersistenceFailureMapsToSafeOutcome(): void
        {
            $persistence = new class implements SuccessPersistenceInterface {
                public function persist(
                    int $linkId,
                    CollectedLinkIdentity $collectedIdentity,
                    string $price,
                    string $currency,
                    \Bitrix\Main\Type\DateTime $collectedAt,
                ): bool {
                    return false;
                }
            };
            $result = $this->service(
                HttpFetchResult::success(200, 'text/html', '<b>187 040 ₽</b>', DataManager::$links[7]['URL']),
                $persistence,
            )->updateLinks([7]);

            self::assertSame(1, $result->persistenceFailureCount());
            self::assertSame('PERSISTENCE_ERROR', $result->outcomes[0]->code);
            self::assertSame('100.00', DataManager::$links[7]['CURRENT_PRICE']);
        }

        private function service(
            HttpFetchResult $fetchResult,
            ?SuccessPersistenceInterface $persistence = null,
            ?\Closure $duringFetch = null,
        ): PriceUpdateService
        {
            $transport = new class($fetchResult, $duringFetch) implements HttpTransportInterface {
                public function __construct(private readonly HttpFetchResult $result, private readonly ?\Closure $duringFetch)
                {
                }

                public function fetch(string $url): HttpFetchResult
                {
                    if ($this->duringFetch !== null) {
                        ($this->duringFetch)();
                    }
                    return $this->result;
                }
            };
            $collector = new HttpHtmlCollector(
                'example.test',
                new HttpPriceExtractionOptions('//b', 'RUB'),
                $transport,
                new DomXPathPriceExtractor(),
            );
            $factory = new class($collector) implements CollectorFactoryInterface {
                public function __construct(private readonly CollectorInterface $collector)
                {
                }

                public function create(array $competitor): CollectorInterface
                {
                    return $this->collector;
                }
            };
            $requestIds = new class implements RequestIdGeneratorInterface {
                public function generate(): string
                {
                    return 'http-regression-request';
                }
            };
            return new PriceUpdateService($factory, $requestIds, $persistence);
        }
    }
}
