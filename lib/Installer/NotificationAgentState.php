<?php

declare(strict_types=1);

namespace KK\PriceWatch\Installer;

use DateTimeInterface;

final class NotificationAgentState
{
    public function __construct(
        public readonly bool $active,
        public readonly int $intervalSeconds,
        public readonly ?string $lastExecution,
        public readonly ?string $nextExecution,
    ) {}

    public static function fromAgentRecord(array $record): self
    {
        return new self(
            ($record['ACTIVE'] ?? 'N') === 'Y',
            (int) ($record['AGENT_INTERVAL'] ?? NotificationAgentInstaller::DEFAULT_INTERVAL_SECONDS),
            self::normalizeDate($record['LAST_EXEC'] ?? null),
            self::normalizeDate($record['NEXT_EXEC'] ?? null),
        );
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d.m.Y H:i:s');
        }
        if (is_object($value) && method_exists($value, 'format')) {
            return (string) $value->format('d.m.Y H:i:s');
        }
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
