<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final readonly class PriceUpdateOutcome
{
    public const SUCCESS = 'success';
    public const ERROR = 'error';
    public const SKIPPED = 'skipped';
    public const PERSISTENCE_FAILURE = 'persistence_failure';

    public function __construct(
        public int $linkId,
        public string $status,
        public ?string $code = null,
        public ?string $message = null,
    ) {
    }
}
