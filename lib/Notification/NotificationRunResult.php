<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

final readonly class NotificationRunResult
{
    public function __construct(
        public bool $disabled = false, public bool $configurationMissing = false, public bool $locked = false,
        public int $scanned = 0, public int $transitions = 0, public bool $sent = false, public bool $failed = false,
    ) {}
    public function isSuccessful(): bool { return !$this->configurationMissing && !$this->locked && !$this->failed; }
    public function toArray(): array { return get_object_vars($this); }
}
