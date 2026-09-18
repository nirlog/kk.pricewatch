<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Notification;

use DateTimeImmutable;
use DateTimeInterface;
use KK\PriceWatch\Notification\{NotificationDigest,NotificationLinkRepositoryInterface,NotificationRuleEvaluator,NotificationRunLockInterface,NotificationRunner,NotificationSettings,NotificationSettingsProviderInterface,NotificationStateRepositoryInterface,NotificationTransportInterface,NotificationTransition,ProductLabelResolverInterface};
use PHPUnit\Framework\TestCase;

final class NotificationRunnerTest extends TestCase
{
    public function testTransportFailureDoesNotAdvanceAndOneDigestContainsOverlap(): void
    {
        $states = new StateMemory(); $transport = new TransportMemory(false); $lock = new LockMemory();
        $runner = $this->runner($states, $transport, $lock, true);
        $result = $runner->run(1, new DateTimeImmutable('2026-09-17 12:00:00 UTC'));
        self::assertTrue($result->failed); self::assertCount(1, $transport->digests); self::assertCount(0, $states->saved);
        self::assertSame(1, $result->scanned);
        self::assertGreaterThan(1, count($transport->digests[0]->transitions)); self::assertTrue($lock->released);
    }

    public function testSuccessAdvancesAndSuppressedRecoveryAdvancesWithoutMail(): void
    {
        $states = new StateMemory(); $transport = new TransportMemory(true); $lock = new LockMemory();
        $first = $this->runner($states, $transport, $lock, true)->run(100, new DateTimeImmutable('2026-09-17 12:00:00 UTC'));
        self::assertTrue($first->sent); self::assertNotEmpty($states->saved); self::assertCount(1, $transport->digests);
        $states->delivered = [1 => ['collection_error' => new \KK\PriceWatch\Notification\NotificationRuleState(true, 'E1')]];
        $links = new LinksMemory([['ID' => 1, 'PRODUCT_ID' => 7, 'COMPETITOR_ID' => 2, 'COMPETITOR_NAME' => 'C', 'URL' => 'https://x.test/?a=1', 'STATUS' => 'success', 'CURRENT_PRICE' => '1.00', 'CURRENCY' => 'RUB', 'LAST_CHECK_AT' => new DateTimeImmutable(), 'LAST_SUCCESS_AT' => new DateTimeImmutable()]]);
        $runner = new NotificationRunner(new SettingsMemory(new NotificationSettings(true, ['a@example.test'], 's1', false)), $links, $states, new NotificationRuleEvaluator(), new ProductsMemory(), $transport, new LockMemory());
        $runner->run(100, new DateTimeImmutable());
        self::assertSame('recovered', $states->saved[array_key_last($states->saved)]->type);
        self::assertCount(1, $transport->digests);
    }

    public function testPendingTransitionsAreBoundedAndRemainderIsPickedUpNextRun(): void
    {
        $rows = [];
        for ($id = 1; $id <= 600; ++$id) {
            $rows[] = ['ID' => $id, 'PRODUCT_ID' => $id, 'COMPETITOR_ID' => 2, 'COMPETITOR_NAME' => 'C',
                'URL' => 'https://example.test/' . $id, 'STATUS' => 'error', 'ERROR_CODE' => 'E1',
                'CURRENT_PRICE' => null, 'CURRENCY' => null, 'LAST_CHECK_AT' => new DateTimeImmutable(), 'LAST_SUCCESS_AT' => null];
        }
        $states = new StateMemory(); $transport = new TransportMemory(true);
        $create = fn(): NotificationRunner => new NotificationRunner(
            new SettingsMemory(new NotificationSettings(true, ['a@example.test'], 's1', true)), new LinksMemory($rows),
            $states, new NotificationRuleEvaluator(), new ProductsMemory(), $transport, new LockMemory()
        );
        $first = $create()->run(100, new DateTimeImmutable('2026-09-17 12:00:00 UTC'));
        self::assertSame(NotificationRunner::MAX_PENDING_TRANSITIONS_PER_RUN, $first->transitions);
        self::assertSame(500, $first->scanned);
        self::assertCount(NotificationRunner::MAX_PENDING_TRANSITIONS_PER_RUN, $transport->digests[0]->transitions);
        $second = $create()->run(100, new DateTimeImmutable('2026-09-17 12:01:00 UTC'));
        self::assertSame(200, $second->transitions);
        self::assertSame(600, $second->scanned);
        self::assertCount(200, $transport->digests[1]->transitions);
    }

    public function testMissingConfigurationIsFailureButDisabledIsSuccessfulNoOp(): void
    {
        $links = new LinksMemory([]); $states = new StateMemory(); $transport = new TransportMemory(true);
        $missing = new NotificationRunner(new SettingsMemory(new NotificationSettings(true, [], '', true)), $links, $states, new NotificationRuleEvaluator(), new ProductsMemory(), $transport, new LockMemory());
        self::assertTrue($missing->run()->configurationMissing);
        self::assertFalse($missing->run()->isSuccessful());
        $disabled = new NotificationRunner(new SettingsMemory(new NotificationSettings(false, [], '', true)), $links, $states, new NotificationRuleEvaluator(), new ProductsMemory(), $transport, new LockMemory());
        self::assertTrue($disabled->run()->isSuccessful());
    }

    public function testEmptyRepositoryReportsNoScannedLinks(): void
    {
        $runner = new NotificationRunner(
            new SettingsMemory(new NotificationSettings(true, ['a@example.test'], 's1', true)),
            new LinksMemory([]), new StateMemory(), new NotificationRuleEvaluator(), new ProductsMemory(),
            new TransportMemory(true), new LockMemory()
        );
        $result = $runner->run();
        self::assertSame(0, $result->scanned);
        self::assertSame(0, $result->transitions);
    }

    private function runner(StateMemory $states, TransportMemory $transport, LockMemory $lock, bool $recovery): NotificationRunner
    {
        $row = ['ID' => 1, 'PRODUCT_ID' => 7, 'COMPETITOR_ID' => 2, 'COMPETITOR_NAME' => 'C', 'URL' => 'https://x.test/?a=1', 'STATUS' => 'error', 'ERROR_CODE' => 'E1', 'ERROR_MESSAGE' => 'secret', 'CURRENT_PRICE' => null, 'CURRENCY' => null, 'LAST_CHECK_AT' => new DateTimeImmutable(), 'LAST_SUCCESS_AT' => null];
        return new NotificationRunner(new SettingsMemory(new NotificationSettings(true, ['a@example.test'], 's1', $recovery)), new LinksMemory([$row]), $states, new NotificationRuleEvaluator(), new ProductsMemory(), $transport, $lock);
    }
}
final class SettingsMemory implements NotificationSettingsProviderInterface { public function __construct(private NotificationSettings $v) {} public function get(): NotificationSettings{return $this->v;} }
final class LinksMemory implements NotificationLinkRepositoryInterface { public function __construct(private array $rows) {} public function maximumActiveId(): ?int{return $this->rows ? max(array_column($this->rows,'ID')) : null;} public function findActiveAfter(int $lastId,int $maximumId,int $limit): array{return array_slice(array_values(array_filter($this->rows,fn($r)=>$r['ID']>$lastId&&$r['ID']<=$maximumId)),0,$limit);} }
final class StateMemory implements NotificationStateRepositoryInterface { public array $delivered=[]; public array $saved=[]; public function findForLinks(array $ids): array{return array_intersect_key($this->delivered,array_flip($ids));} public function saveDelivered(array $transitions,DateTimeInterface $at): void{foreach($transitions as $transition){$this->saved[]=$transition;$this->delivered[$transition->linkId][$transition->ruleCode]=$transition->current;}} }
final class TransportMemory implements NotificationTransportInterface { public array $digests=[]; public function __construct(private bool $success){} public function send(NotificationDigest $digest,array $recipients,string $siteId): bool{$this->digests[]=$digest;return $this->success;} }
final class ProductsMemory implements ProductLabelResolverInterface { public function resolve(array $ids): array{return [7=>'Product'];} }
final class LockMemory implements NotificationRunLockInterface { public bool $released=false; public function acquire(): bool{return true;} public function release(): void{$this->released=true;} }
