<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Scheduling;

use KK\PriceWatch\Scheduling\ActiveLinkRepositoryInterface;
use KK\PriceWatch\Scheduling\ScheduledPriceUpdateRunner;
use KK\PriceWatch\Scheduling\ScheduledRunLockInterface;
use KK\PriceWatch\Service\PriceUpdateBatchResult;
use KK\PriceWatch\Service\PriceUpdateOutcome;
use KK\PriceWatch\Service\PriceUpdateServiceInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScheduledPriceUpdateRunnerTest extends TestCase
{
    public function testUsesStableHorizonAndKeysetChunksAndAggregates(): void
    {
        $repository = new FakeRepository([1, 2, 4, 8]);
        $service = new FakeService();
        $lock = new FakeLock();

        $result = (new ScheduledPriceUpdateRunner($service, $repository, $lock))->run(2);

        self::assertSame([[1, 2], [4, 8]], $service->calls);
        self::assertSame([[0, 8, 2], [2, 8, 2]], $repository->queries);
        self::assertSame(4, $result->selected);
        self::assertSame(2, $result->batches);
        self::assertSame(4, $result->requested);
        self::assertSame(1, $result->success);
        self::assertSame(3, $result->errors);
        self::assertTrue($lock->released);
    }

    public function testNoActiveLinksDoesNotCallService(): void
    {
        $service = new FakeService();
        $result = (new ScheduledPriceUpdateRunner($service, new FakeRepository([]), new FakeLock()))->run();
        self::assertSame([], $service->calls);
        self::assertSame(0, $result->selected);
    }

    public function testLockContentionIsNoOpAndDoesNotUnlock(): void
    {
        $lock = new FakeLock(false);
        $repository = new FakeRepository([1]);
        $service = new FakeService();
        $result = (new ScheduledPriceUpdateRunner($service, $repository, $lock))->run();
        self::assertTrue($result->locked);
        self::assertSame(0, $repository->maximumCalls);
        self::assertFalse($lock->released);
    }

    public function testThrownBatchIsIsolatedFromLaterChunk(): void
    {
        $service = new FakeService(throwOnCall: 1);
        $result = (new ScheduledPriceUpdateRunner($service, new FakeRepository([1, 2, 3]), new FakeLock()))->run(2);
        self::assertSame([[1, 2], [3]], $service->calls);
        self::assertSame(1, $result->batchFailures);
        self::assertSame(1, $result->requested);
        self::assertFalse($result->globalFailure);
    }

    public function testSelectionFailureIsGenericAndReleasesLock(): void
    {
        $repository = new FakeRepository([1]);
        $repository->throwOnQuery = true;
        $lock = new FakeLock();
        $result = (new ScheduledPriceUpdateRunner(new FakeService(), $repository, $lock))->run();
        self::assertTrue($result->globalFailure);
        self::assertTrue($lock->released);
        self::assertArrayNotHasKey('message', $result->toArray());
    }

    public function testRejectsOutOfRangeBatchSizes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ScheduledPriceUpdateRunner(new FakeService(), new FakeRepository([]), new FakeLock()))->run(1001);
    }
}

final class FakeRepository implements ActiveLinkRepositoryInterface
{
    public int $maximumCalls = 0;
    public bool $throwOnQuery = false;
    public array $queries = [];
    public function __construct(private array $ids) {}
    public function maximumActiveId(): ?int { ++$this->maximumCalls; return $this->ids === [] ? null : max($this->ids); }
    public function findActiveIdsAfter(int $lastId, int $maximumId, int $limit): array
    {
        if ($this->throwOnQuery) { throw new RuntimeException('secret database detail'); }
        $this->queries[] = [$lastId, $maximumId, $limit];
        return array_slice(array_values(array_filter($this->ids, static fn(int $id): bool => $id > $lastId && $id <= $maximumId)), 0, $limit);
    }
}

final class FakeService implements PriceUpdateServiceInterface
{
    public array $calls = [];
    private int $callNumber = 0;
    public function __construct(private int $throwOnCall = 0) {}
    public function updateLinks(array $linkIds): PriceUpdateBatchResult
    {
        $this->calls[] = $linkIds;
        ++$this->callNumber;
        if ($this->callNumber === $this->throwOnCall) { throw new RuntimeException('secret collector detail'); }
        $outcomes = array_map(static fn(int $id): PriceUpdateOutcome => new PriceUpdateOutcome(
            $id,
            $id % 2 ? PriceUpdateOutcome::SUCCESS : PriceUpdateOutcome::ERROR
        ), $linkIds);
        return new PriceUpdateBatchResult(count($linkIds), $outcomes);
    }
}

final class FakeLock implements ScheduledRunLockInterface
{
    public bool $released = false;
    public function __construct(private bool $available = true) {}
    public function acquire(): bool { return $this->available; }
    public function release(): void { $this->released = true; }
}
