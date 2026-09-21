<?php

declare(strict_types=1);

namespace {
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
        protected function setUp(): void { CAgent::$rows = []; CAgent::$nextId = 1; }

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

        public function testInstallPreservesInactiveAndNormalizesInvalidInterval(): void
        {
            $this->add('N', 0);
            (new NotificationAgentInstaller())->install();
            self::assertSame('N', CAgent::$rows[1]['ACTIVE']);
            self::assertSame(3600, CAgent::$rows[1]['AGENT_INTERVAL']);
        }

        public function testConfigureEnablesChangesIntervalAndDisables(): void
        {
            $installer = new NotificationAgentInstaller();
            $installer->install();
            $installer->configure(true, 300);
            self::assertTrue($installer->getState()->active);
            self::assertSame(300, $installer->getState()->intervalSeconds);
            $installer->configure(false, 86400);
            self::assertFalse($installer->getState()->active);
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
