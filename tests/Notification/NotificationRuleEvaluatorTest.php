<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Notification;

use DateTimeImmutable;
use KK\PriceWatch\Notification\NotificationRuleEvaluator;
use KK\PriceWatch\Notification\NotificationRuleState;
use KK\PriceWatch\Notification\NotificationTransition;
use PHPUnit\Framework\TestCase;

final class NotificationRuleEvaluatorTest extends TestCase
{
    public function testRulesOverlapAndNeverUseErrorMessage(): void
    {
        $now = new DateTimeImmutable('2026-09-17 12:00:00 UTC');
        $row = ['ID' => 1, 'STATUS' => 'error', 'ERROR_CODE' => 'TIMEOUT', 'ERROR_MESSAGE' => 'secret response',
            'CURRENT_PRICE' => null, 'CURRENCY' => null, 'LAST_SUCCESS_AT' => null, 'LAST_CHECK_AT' => $now];
        $states = (new NotificationRuleEvaluator())->evaluate($row, $now);
        self::assertTrue($states[NotificationRuleEvaluator::COLLECTION_ERROR]->active);
        self::assertSame('TIMEOUT', $states[NotificationRuleEvaluator::COLLECTION_ERROR]->fingerprint);
        self::assertTrue($states[NotificationRuleEvaluator::NO_SUCCESS_PRICE]->active);
        self::assertFalse($states[NotificationRuleEvaluator::STALE]->active);
        self::assertStringNotContainsString('secret', implode('|', array_map(static fn($state) => (string) $state->fingerprint, $states)));
    }

    public function testNeverAttemptedDoesNotAlertAndStaleUsesSharedSemantics(): void
    {
        $now = new DateTimeImmutable('2026-09-17 12:00:00 UTC'); $evaluator = new NotificationRuleEvaluator();
        $new = $evaluator->evaluate(['STATUS' => 'new', 'LAST_CHECK_AT' => null], $now);
        self::assertFalse($new[NotificationRuleEvaluator::NO_SUCCESS_PRICE]->active);
        $stale = $evaluator->evaluate(['STATUS' => 'success', 'CURRENT_PRICE' => '1.00', 'CURRENCY' => 'RUB',
            'LAST_SUCCESS_AT' => $now->modify('-86401 seconds'), 'LAST_CHECK_AT' => $now], $now);
        self::assertTrue($stale[NotificationRuleEvaluator::STALE]->active);
    }

    public function testTransitionDeduplicationChangeAndRecovery(): void
    {
        $evaluator = new NotificationRuleEvaluator(); $link = ['ID' => 1];
        self::assertNull($evaluator->transition(1, 'x', new NotificationRuleState(true, 'E1'), new NotificationRuleState(true, 'E1'), $link));
        self::assertSame(NotificationTransition::PROBLEM_CHANGED, $evaluator->transition(1, 'x', new NotificationRuleState(true, 'E2'), new NotificationRuleState(true, 'E1'), $link)?->type);
        self::assertSame(NotificationTransition::RECOVERED, $evaluator->transition(1, 'x', new NotificationRuleState(false), new NotificationRuleState(true, 'E1'), $link)?->type);
    }
}
