<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use KK\PriceWatch\Service\PriceHistoryDecision;
use PHPUnit\Framework\TestCase;

final class PriceHistoryDecisionTest extends TestCase
{
    public function testBaselinePriceAndCurrencyChangesAppendButUnchangedSuccessDoesNot(): void
    {
        self::assertTrue(PriceHistoryDecision::shouldAppend(null, null, '100000.00', 'RUB'));
        self::assertFalse(PriceHistoryDecision::shouldAppend('100000.00', 'RUB', '100000.00', 'RUB'));
        self::assertFalse(PriceHistoryDecision::shouldAppend('187040', 'RUB', '187040.00', 'RUB'));
        self::assertFalse(PriceHistoryDecision::shouldAppend('00187040.0', 'RUB', '187040.00', 'RUB'));
        self::assertTrue(PriceHistoryDecision::shouldAppend('100000.00', 'RUB', '95000.00', 'RUB'));
        self::assertTrue(PriceHistoryDecision::shouldAppend('95000.00', 'RUB', '95000.00', 'USD'));
    }

    public function testCanonicalizationNeverNeedsFloatSemantics(): void
    {
        self::assertSame('1.00', PriceHistoryDecision::canonicalPrice('1'));
        self::assertSame('1.20', PriceHistoryDecision::canonicalPrice('1.2'));
        self::assertSame('1.20', PriceHistoryDecision::canonicalPrice('001.2000'));
        self::assertSame('1.23', PriceHistoryDecision::canonicalPrice('1.234'));
        self::assertSame('1.24', PriceHistoryDecision::canonicalPrice('1.235'));
        self::assertSame('10.00', PriceHistoryDecision::canonicalPrice('9.999'));
        self::assertSame('0.05', PriceHistoryDecision::canonicalPrice('0.05'));
    }

    public function testRepeatedValuesEquivalentAfterPersistenceRoundingDoNotAppend(): void
    {
        self::assertFalse(PriceHistoryDecision::shouldAppend('1.23', 'RUB', '1.234', 'RUB'));
        self::assertFalse(PriceHistoryDecision::shouldAppend('1.24', 'RUB', '1.235', 'RUB'));
        self::assertFalse(PriceHistoryDecision::shouldAppend('1.20', 'RUB', '001.2000', 'RUB'));
    }

    public function testLastAppendedStateControlsDecisionRegardlessOfCollectionTime(): void
    {
        $appended = [
            ['id' => 10, 'collected_at' => '10:00:02', 'price' => '90.00'],
            ['id' => 11, 'collected_at' => '10:00:01', 'price' => '100.00'],
        ];
        usort($appended, static fn(array $left, array $right): int => $right['id'] <=> $left['id']);

        self::assertFalse(PriceHistoryDecision::shouldAppend($appended[0]['price'], 'RUB', '100.000', 'RUB'));
        self::assertTrue(PriceHistoryDecision::shouldAppend($appended[0]['price'], 'RUB', '90.00', 'RUB'));
    }

    public function testReturnToAnEarlierPriceRemainsAChange(): void
    {
        $history = [];
        foreach (['100000.00', '95000.00', '100000.00'] as $price) {
            $previous = $history === [] ? null : $history[array_key_last($history)];
            if (PriceHistoryDecision::shouldAppend($previous, $previous === null ? null : 'RUB', $price, 'RUB')) {
                $history[] = $price;
            }
        }
        self::assertSame(['100000.00', '95000.00', '100000.00'], $history);
    }
}
