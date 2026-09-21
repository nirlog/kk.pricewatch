<?php

declare(strict_types=1);

namespace KK\PriceWatch\Installer;

use CAgent;
use Closure;
use KK\PriceWatch\Agent\NotificationAgent;
use RuntimeException;

final class NotificationAgentInstaller
{
    public const MODULE_ID = 'kk.pricewatch';
    public const DEFAULT_INTERVAL_SECONDS = 3600;
    public const MIN_INTERVAL_SECONDS = 300;
    public const MAX_INTERVAL_SECONDS = 86400;

    private Closure $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn(): int => time();
    }

    public function install(): void
    {
        $records = $this->findAll();
        if ($records === []) {
            $id = CAgent::AddAgent(
                NotificationAgent::INVOCATION,
                self::MODULE_ID,
                'N',
                self::DEFAULT_INTERVAL_SECONDS,
                '',
                'N'
            );
            if (!$id) {
                throw new RuntimeException('Unable to create notification agent.');
            }
            return;
        }

        $primary = $this->selectPrimary($records);
        $interval = (int) ($primary['AGENT_INTERVAL'] ?? 0);
        if (!$this->isValidIntervalSeconds($interval)
            && !CAgent::Update((int) $primary['ID'], ['AGENT_INTERVAL' => self::DEFAULT_INTERVAL_SECONDS])) {
            throw new RuntimeException('Unable to normalize notification agent interval.');
        }

        foreach ($records as $duplicate) {
            if ((int) $duplicate['ID'] === (int) $primary['ID']) {
                continue;
            }
            if (!CAgent::Delete((int) $duplicate['ID'])) {
                throw new RuntimeException('Unable to remove duplicate notification agent.');
            }
        }
    }

    public function getState(): NotificationAgentState
    {
        $this->install();
        $record = $this->findAll()[0] ?? null;
        if ($record === null) {
            throw new RuntimeException('Notification agent is unavailable.');
        }

        return NotificationAgentState::fromAgentRecord($record);
    }

    public function configure(bool $active, int $intervalSeconds): void
    {
        if (!$this->isValidIntervalSeconds($intervalSeconds)) {
            throw new RuntimeException('Invalid notification agent interval.');
        }

        $this->install();
        $record = $this->findAll()[0] ?? null;
        if ($record === null) {
            throw new RuntimeException('Unable to configure notification agent.');
        }

        $wasActive = ($record['ACTIVE'] ?? 'N') === 'Y';
        $previousInterval = (int) ($record['AGENT_INTERVAL'] ?? 0);
        $activeChanged = $wasActive !== $active;
        $intervalChanged = $previousInterval !== $intervalSeconds;
        if (!$activeChanged && !$intervalChanged) {
            return;
        }

        $fields = [
            'ACTIVE' => $active ? 'Y' : 'N',
            'AGENT_INTERVAL' => $intervalSeconds,
        ];
        if ($intervalChanged || (!$wasActive && $active)) {
            // A changed schedule starts a full configured interval from now.
            // ConvertTimeStamp(..., 'FULL') is the legacy Bitrix CAgent date format.
            $fields['NEXT_EXEC'] = ConvertTimeStamp(
                ($this->clock)() + \CTimeZone::GetOffset() + $intervalSeconds,
                'FULL'
            );
        }
        if (!CAgent::Update((int) $record['ID'], $fields)) {
            throw new RuntimeException('Unable to configure notification agent.');
        }

        $state = $this->getState();
        if ($state->active !== $active || $state->intervalSeconds !== $intervalSeconds) {
            throw new RuntimeException('Notification agent configuration was not applied.');
        }
    }

    public function uninstall(): void
    {
        CAgent::RemoveAgent(NotificationAgent::INVOCATION, self::MODULE_ID);
    }

    private function findAll(): array
    {
        $result = CAgent::GetList(
            ['ID' => 'ASC'],
            ['MODULE_ID' => self::MODULE_ID, '=NAME' => NotificationAgent::INVOCATION]
        );
        $records = [];
        while ($record = $result->Fetch()) {
            $records[] = $record;
        }
        return $records;
    }

    private function selectPrimary(array $records): array
    {
        foreach ($records as $record) {
            if (($record['ACTIVE'] ?? 'N') === 'Y') {
                return $record;
            }
        }
        return $records[0];
    }

    private function isValidIntervalSeconds(int $interval): bool
    {
        return $interval >= self::MIN_INTERVAL_SECONDS
            && $interval <= self::MAX_INTERVAL_SECONDS
            && $interval % 60 === 0;
    }
}
