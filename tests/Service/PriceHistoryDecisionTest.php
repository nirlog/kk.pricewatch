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
        self::assertTrue(PriceHistoryDecision::shouldAppend('100000.00', 'RUB', '95000.00', 'RUB'));
        self::assertTrue(PriceHistoryDecision::shouldAppend('95000.00', 'RUB', '95000.00', 'USD'));
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
