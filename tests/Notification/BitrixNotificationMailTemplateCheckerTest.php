<?php

declare(strict_types=1);

namespace {
    final class BitrixNotificationMailTemplateQueryStub
    {
        /** @param list<array<string, mixed>> $rows */
        public function __construct(private array $rows) {}

        /** @return array<string, mixed>|false */
        public function Fetch(): array|false
        {
            return array_shift($this->rows) ?? false;
        }
    }

    final class BitrixNotificationMailTemplateEventMessageStub
    {
        /** @var list<array<string, mixed>> */
        public static array $rows = [];

        /** @param array<string, mixed> $filter */
        public static function GetList(mixed &$by, mixed &$order, array $filter): BitrixNotificationMailTemplateQueryStub
        {
            return new BitrixNotificationMailTemplateQueryStub(self::$rows);
        }
    }

    if (!class_exists('CEventMessage')) {
        class_alias(BitrixNotificationMailTemplateEventMessageStub::class, 'CEventMessage');
    }
}

namespace KK\PriceWatch\Tests\Notification {
    use KK\PriceWatch\Notification\BitrixNotificationMailTemplateChecker;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    final class BitrixNotificationMailTemplateCheckerTest extends TestCase
    {
        protected function setUp(): void
        {
            \BitrixNotificationMailTemplateEventMessageStub::$rows = [];
        }

        public function testActiveCompleteTemplateForSiteExists(): void
        {
            \BitrixNotificationMailTemplateEventMessageStub::$rows = [$this->validRow()];

            self::assertTrue((new BitrixNotificationMailTemplateChecker())->existsForSite('s1'));
        }

        /** @param array<string, mixed> $changes */
        #[DataProvider('invalidTemplateProvider')]
        public function testInvalidTemplateDoesNotExist(array $changes): void
        {
            \BitrixNotificationMailTemplateEventMessageStub::$rows = [array_replace($this->validRow(), $changes)];

            self::assertFalse((new BitrixNotificationMailTemplateChecker())->existsForSite('s1'));
        }

        /** @return iterable<string, array{array<string, mixed>}> */
        public static function invalidTemplateProvider(): iterable
        {
            yield 'empty subject' => [['SUBJECT' => '']];
            yield 'whitespace-only subject' => [['SUBJECT' => " \t\n"]];
            yield 'missing subject' => [['SUBJECT' => null]];
            yield 'empty body' => [['MESSAGE' => '']];
            yield 'whitespace-only body' => [['MESSAGE' => " \t\n"]];
            yield 'missing body' => [['MESSAGE' => null]];
            yield 'inactive template' => [['ACTIVE' => 'N']];
            yield 'wrong site' => [['LID' => ['s2']]];
        }

        /** @return array<string, mixed> */
        private function validRow(): array
        {
            return [
                'ACTIVE' => 'Y',
                'LID' => ['s1'],
                'SUBJECT' => 'Notification subject',
                'MESSAGE' => '<p>Notification body</p>',
            ];
        }
    }
}
