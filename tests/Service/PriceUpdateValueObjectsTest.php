<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use KK\PriceWatch\Service\PriceUpdateBatchResult;
use KK\PriceWatch\Service\PriceUpdateOutcome;
use KK\PriceWatch\Service\RandomRequestIdGenerator;
use PHPUnit\Framework\TestCase;

final class PriceUpdateValueObjectsTest extends TestCase
{
    public function testRequestIdsAreUuidStyleAndDistinct(): void
    {
        $generator = new RandomRequestIdGenerator();
        $first = $generator->generate();
        self::assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $first);
        self::assertNotSame($first, $generator->generate());
    }

    public function testBatchCountsOutcomes(): void
    {
        $result = new PriceUpdateBatchResult(4, [
            new PriceUpdateOutcome(1, PriceUpdateOutcome::SUCCESS), new PriceUpdateOutcome(2, PriceUpdateOutcome::ERROR),
            new PriceUpdateOutcome(3, PriceUpdateOutcome::SKIPPED), new PriceUpdateOutcome(4, PriceUpdateOutcome::PERSISTENCE_FAILURE),
        ]);
        self::assertSame(1, $result->successCount());
        self::assertSame(1, $result->errorCount());
        self::assertSame(1, $result->skippedCount());
        self::assertSame(1, $result->persistenceFailureCount());
    }
}
