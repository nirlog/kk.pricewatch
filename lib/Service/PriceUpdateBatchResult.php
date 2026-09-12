<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final readonly class PriceUpdateBatchResult
{
    /** @var list<PriceUpdateOutcome> */
    public array $outcomes;

    /** @param list<PriceUpdateOutcome> $outcomes */
    public function __construct(public int $requestedCount, array $outcomes)
    {
        $this->outcomes = array_values($outcomes);
    }

    public function successCount(): int { return $this->count(PriceUpdateOutcome::SUCCESS); }
    public function errorCount(): int { return $this->count(PriceUpdateOutcome::ERROR); }
    public function skippedCount(): int { return $this->count(PriceUpdateOutcome::SKIPPED); }
    public function persistenceFailureCount(): int { return $this->count(PriceUpdateOutcome::PERSISTENCE_FAILURE); }

    private function count(string $status): int
    {
        return count(array_filter($this->outcomes, static fn(PriceUpdateOutcome $outcome): bool => $outcome->status === $status));
    }
}
