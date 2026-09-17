<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

final readonly class NotificationRuleState
{
    public function __construct(public bool $active, public ?string $fingerprint = null) {}
}
