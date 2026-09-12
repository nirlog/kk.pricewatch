<?php

declare(strict_types=1);

namespace KK\PriceWatch\Scheduling;

use InvalidArgumentException;
use KK\PriceWatch\Service\PriceUpdateServiceInterface;
use Throwable;

final class ScheduledPriceUpdateRunner
{
    public const DEFAULT_BATCH_SIZE = 100;
    public const MAX_BATCH_SIZE = 1000;

    public function __construct(
        private readonly PriceUpdateServiceInterface $priceUpdateService,
        private readonly ActiveLinkRepositoryInterface $links,
        private readonly ScheduledRunLockInterface $lock,
    ) {
    }

    public function run(int $batchSize = self::DEFAULT_BATCH_SIZE): ScheduledRunResult
    {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('Batch size must be between 1 and 1000.');
        }

        try {
            $acquired = $this->lock->acquire();
        } catch (Throwable) {
            return new ScheduledRunResult(globalFailure: true);
        }
        if (!$acquired) {
            return new ScheduledRunResult(locked: true);
        }

        $selected = $batches = $requested = $success = $errors = $skipped = $persistenceFailures = $batchFailures = 0;
        $globalFailure = false;
        try {
            try {
                $maximumId = $this->links->maximumActiveId();
            } catch (Throwable) {
                $globalFailure = true;
                $maximumId = null;
            }

            $lastId = 0;
            while (!$globalFailure && $maximumId !== null && $lastId < $maximumId) {
                try {
                    $ids = $this->links->findActiveIdsAfter($lastId, $maximumId, $batchSize);
                } catch (Throwable) {
                    $globalFailure = true;
                    break;
                }
                if ($ids === []) {
                    break;
                }

                $selected += count($ids);
                $lastId = $ids[array_key_last($ids)];
                ++$batches;
                try {
                    $result = $this->priceUpdateService->updateLinks($ids);
                    $requested += $result->requestedCount;
                    $success += $result->successCount();
                    $errors += $result->errorCount();
                    $skipped += $result->skippedCount();
                    $persistenceFailures += $result->persistenceFailureCount();
                } catch (Throwable) {
                    ++$batchFailures;
                }
            }
        } finally {
            try {
                $this->lock->release();
            } catch (Throwable) {
                $globalFailure = true;
            }
        }

        return new ScheduledRunResult(false, $selected, $batches, $requested, $success, $errors, $skipped, $persistenceFailures, $batchFailures, $globalFailure);
    }
}
