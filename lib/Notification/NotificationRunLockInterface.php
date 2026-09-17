<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;
interface NotificationRunLockInterface { public function acquire(): bool; public function release(): void; }
