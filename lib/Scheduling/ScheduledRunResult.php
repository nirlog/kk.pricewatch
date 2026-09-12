<?php

declare(strict_types=1);

namespace KK\PriceWatch\Scheduling;

final readonly class ScheduledRunResult
{
    public function __construct(
        public bool $locked = false,
        public int $selected = 0,
        public int $batches = 0,
        public int $requested = 0,
        public int $success = 0,
        public int $errors = 0,
        public int $skipped = 0,
        public int $persistenceFailures = 0,
        public int $batchFailures = 0,
        public bool $globalFailure = false,
    ) {
    }

    /** @return array<string, bool|int> */
    public function toArray(): array
    {
        return [
            'locked' => $this->locked,
            'selected' => $this->selected,
            'batches' => $this->batches,
            'requested' => $this->requested,
            'success' => $this->success,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
            'persistence_failures' => $this->persistenceFailures,
            'batch_failures' => $this->batchFailures,
            'global_failure' => $this->globalFailure,
        ];
    }
}
