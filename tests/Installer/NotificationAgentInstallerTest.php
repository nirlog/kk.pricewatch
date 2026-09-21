<?php

declare(strict_types=1);

namespace {
    if (!function_exists('ConvertTimeStamp')) {
        function ConvertTimeStamp(int $timestamp, string $type): string
        {
            return date('d.m.Y H:i:s', $timestamp);
        }
    }

    final class NotificationAgentResultStub
    {
        public function __construct(private array $rows) {}
        public function Fetch(): array|false { return array_shift($this->rows) ?? false; }
    }

    if (!class_exists('CAgent')) {
        final class CAgent
        {
            public static array $rows = [];
            public static int $nextId = 1;
            public static array $updates = [];
            public static function GetList(array $order, array $filter): NotificationAgentResultStub
            {
                $rows = array_values(array_filter(self::$rows, static fn(array $row): bool =>
                    $row['MODULE_ID'] === $filter['MODULE_ID'] && $row['NAME'] === $filter['=NAME']));
                usort($rows, static fn(array $a, array $b): int => $a['ID'] <=> $b['ID']);
                return new NotificationAgentResultStub($rows);
            }
            public static function AddAgent(string $name, string $moduleId, string $period, int $interval, string $dateCheck, string $active): int
            {
                $id = self::$nextId++;
                self::$rows[$id] = ['ID' => $id, 'NAME' => $name, 'MODULE_ID' => $moduleId, 'ACTIVE' => $active,
                    'AGENT_INTERVAL' => $interval, 'LAST_EXEC' => null, 'NEXT_EXEC' => null];
                return $id;
            }
            public static function Update(int $id, array $fields): bool
            {
                if (!isset(self::$rows[$id])) return false;
                self::$updates[] = ['ID' => $id, 'FIELDS' => $fields];
                self::$rows[$id] = array_replace(self::$rows[$id], $fields);
                return true;
            }
            public static function Delete(int $id): bool { unset(self::$rows[$id]); return true; }
            public static function RemoveAgent(string $name, string $moduleId): void
            {
                self::$rows = array_filter(self::$rows, static fn(array $row): bool => $row['NAME'] !== $name || $row['MODULE_ID'] !== $moduleId);
            }
        }
    }
}

namespace KK\PriceWatch\Tests\Installer {
    use CAgent;
    use KK\PriceWatch\Agent\NotificationAgent;
    use KK\PriceWatch\Installer\NotificationAgentInstaller;
    use PHPUnit\Framework\TestCase;

    final class NotificationAgentInstallerTest extends TestCase
    {
        protected function setUp(): void { CAgent::$rows = []; CAgent::$nextId = 1; CAgent::$updates = []; }

        public function testFreshAndRepeatedInstallCreateOneInactiveHourlyAgent(): void
        {
            $installer = new NotificationAgentInstaller();
            $installer->install(); $installer->install();
            self::assertCount(1, CAgent::$rows);
            self::assertSame('N', CAgent::$rows[1]['ACTIVE']);
            self::assertSame(3600, CAgent::$rows[1]['AGENT_INTERVAL']);
        }

        public function testInstallPreservesPrimaryStateAndValidIntervalAndDeletesDuplicates(): void
        {
            $this->add('Y', 7200); $this->add('N', 3600);
            (new NotificationAgentInstaller())->install();
            self::assertCount(1, CAgent::$rows);
            self::assertSame('Y', CAgent::$rows[1]['ACTIVE']);
            self::assertSame(7200, CAgent::$rows[1]['AGENT_INTERVAL']);
        }

        public function testInstallPrefersActiveDuplicateEvenWhenInactiveRecordHasLowerId(): void
        {
            $this->add('N', 3600); $this->add('Y', 7200);
            (new NotificationAgentInstaller())->install();
            self::assertCount(1, CAgent::$rows);
            self::assertArrayHasKey(2, CAgent::$rows);
            self::assertSame('Y', CAgent::$rows[2]['ACTIVE']);
            self::assertSame(7200, CAgent::$rows[2]['AGENT_INTERVAL']);
        }

        public function testInstallKeepsLowestIdWhenSeveralDuplicatesAreActive(): void
        {
            $this->add('Y', 3600); $this->add('Y', 7200);
            (new NotificationAgentInstaller())->install();
            self::assertSame([1], array_keys(CAgent::$rows));
            self::assertSame(3600, CAgent::$rows[1]['AGENT_INTERVAL']);
        }

        public function testInstallPreservesInactiveAndNormalizesInvalidInterval(): void
        {
            $this->add('N', 0);
            (new NotificationAgentInstaller())->install();
            self::assertSame('N', CAgent::$rows[1]['ACTIVE']);
            self::assertSame(3600, CAgent::$rows[1]['AGENT_INTERVAL']);
        }

        public function testInstallNormalizesIntervalsThatAreNotWholeMinutes(): void
        {
            foreach ([301, 3599] as $invalidInterval) {
                $this->setUp();
                $this->add('N', $invalidInterval);
                (new NotificationAgentInstaller())->install();
                self::assertSame(3600, CAgent::$rows[1]['AGENT_INTERVAL']);
            }
        }

        public function testChangingIntervalReschedulesFromNow(): void
        {
            $this->add('Y', 3600);
            CAgent::$rows[1]['NEXT_EXEC'] = 'old schedule';
            $installer = new NotificationAgentInstaller(static fn(): int => 1_700_000_000);
            $installer->configure(true, 7200);
            self::assertTrue($installer->getState()->active);
            self::assertSame(7200, $installer->getState()->intervalSeconds);
            self::assertSame(date('d.m.Y H:i:s', 1_700_007_200), CAgent::$rows[1]['NEXT_EXEC']);
        }

        public function testEnablingInactiveAgentSchedulesFirstRunAfterInterval(): void
        {
            $this->add('N', 3600);
            $installer = new NotificationAgentInstaller(static fn(): int => 1_700_000_000);
            $installer->configure(true, 3600);
            self::assertSame('Y', CAgent::$rows[1]['ACTIVE']);
            self::assertSame(date('d.m.Y H:i:s', 1_700_003_600), CAgent::$rows[1]['NEXT_EXEC']);
        }

        public function testUnchangedConfigurationDoesNotUpdateOrShiftSchedule(): void
        {
            $this->add('Y', 3600);
            CAgent::$rows[1]['NEXT_EXEC'] = '21.09.2026 10:00:00';
            $installer = new NotificationAgentInstaller(static fn(): int => 1_700_000_000);
            $installer->configure(true, 3600);
            self::assertSame([], CAgent::$updates);
            self::assertSame('21.09.2026 10:00:00', CAgent::$rows[1]['NEXT_EXEC']);
        }

        public function testDisablingAgentDoesNotShiftUnchangedSchedule(): void
        {
            $this->add('Y', 3600);
            CAgent::$rows[1]['NEXT_EXEC'] = '21.09.2026 10:00:00';
            (new NotificationAgentInstaller(static fn(): int => 1_700_000_000))->configure(false, 3600);
            self::assertSame('N', CAgent::$rows[1]['ACTIVE']);
            self::assertSame('21.09.2026 10:00:00', CAgent::$rows[1]['NEXT_EXEC']);
            self::assertArrayNotHasKey('NEXT_EXEC', CAgent::$updates[0]['FIELDS']);
        }

        public function testConfigureAcceptsOnlyWholeMinuteIntervals(): void
        {
            $installer = new NotificationAgentInstaller();
            foreach ([300, 3600, 86400] as $validInterval) {
                $installer->configure(false, $validInterval);
            }
            foreach ([301, 3599] as $invalidInterval) {
                try {
                    $installer->configure(false, $invalidInterval);
                    self::fail('Non-minute interval must be rejected.');
                } catch (\RuntimeException) {
                    self::assertTrue(true);
                }
            }
        }

        public function testUninstallRemovesAgent(): void
        {
            $installer = new NotificationAgentInstaller(); $installer->install(); $installer->uninstall();
            self::assertSame([], CAgent::$rows);
        }

        private function add(string $active, int $interval): void
        {
            CAgent::AddAgent(NotificationAgent::INVOCATION, NotificationAgentInstaller::MODULE_ID, 'N', $interval, '', $active);
        }
    }
}
