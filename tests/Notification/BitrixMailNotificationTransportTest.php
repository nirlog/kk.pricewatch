<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Notification;

use KK\PriceWatch\Notification\MailSendResult;
use PHPUnit\Framework\TestCase;

final class BitrixMailNotificationTransportTest extends TestCase
{
    public function testBitrixStringSendResultsAreHandledWithoutIntegerCoercion(): void
    {
        self::assertTrue(MailSendResult::isSuccess('Y', 'Y'));
        self::assertTrue(MailSendResult::isSuccess('BITRIX_SUCCESS', 'BITRIX_SUCCESS'));
        self::assertFalse(MailSendResult::isSuccess('F', 'Y'));
        self::assertFalse(MailSendResult::isSuccess('0', 'Y'));
    }

    public function testConfidentialDigestDisablesMainModuleDuplicateRecipient(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Notification/BitrixMailNotificationTransport.php');
        self::assertStringContainsString("->send(self::EVENT_NAME, \$siteId, \$fields, 'N')", $source);
        self::assertStringContainsString('Event::SEND_RESULT_SUCCESS', $source);
        self::assertStringNotContainsString('(int) $result', $source);
        self::assertDoesNotMatchRegularExpression('/\(int\)\s*\$this->sender->send\s*\(/', $source);
    }

    public function testMailLocalizationPathsMatchSourceCaseExactly(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['en', 'ru'] as $language) {
            self::assertFileExists($root . '/lang/' . $language . '/lib/Installer/NotificationMailInstaller.php');
            self::assertFileExists($root . '/lang/' . $language . '/lib/Notification/BitrixMailNotificationTransport.php');
            self::assertFileDoesNotExist($root . '/lang/' . $language . '/lib/installer/notificationmailinstaller.php');
            self::assertFileDoesNotExist($root . '/lang/' . $language . '/lib/notification/bitrixmailnotificationtransport.php');
        }
    }

    public function testInstallerRejectsMissingLocalizationAndRepairsOnlyEmptyTemplateFields(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Installer/NotificationMailInstaller.php');

        self::assertStringContainsString('Loc::loadLanguageFile(__FILE__, $languageId)', $source);
        self::assertStringContainsString('throw new \\RuntimeException', $source);
        self::assertStringContainsString("trim(\$messages[\$code]) === ''", $source);
        self::assertStringContainsString("trim(\$message['SUBJECT']) === ''", $source);
        self::assertStringContainsString("trim(\$message['MESSAGE']) === ''", $source);
        self::assertStringContainsString('->Update($messageId, $fields)', $source);
    }

    public function testPatchUpdaterRunsIdempotentMailTemplateRepair(): void
    {
        $root = dirname(__DIR__, 2);
        $updater = (string) file_get_contents($root . '/install/updates/0.15.1/updater.php');

        self::assertStringContainsString('(new NotificationMailInstaller())->install()', $updater);
        self::assertStringNotContainsString('SchemaInstaller', $updater);
    }

    public function testDigestLabelsAreLocalizedInBothLanguages(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['en', 'ru'] as $language) {
            $messages = (string) file_get_contents($root . '/lang/' . $language . '/lib/Notification/BitrixMailNotificationTransport.php');
            foreach (['DIGEST_PRODUCT', 'RULE_COLLECTION_ERROR', 'RULE_STALE', 'RULE_NO_SUCCESS_PRICE',
                'TRANSITION_PROBLEM_STARTED', 'TRANSITION_PROBLEM_CHANGED', 'TRANSITION_RECOVERED'] as $key) {
                self::assertStringContainsString('KK_PRICEWATCH_' . $key, $messages, $language . ':' . $key);
            }
            $template = (string) file_get_contents($root . '/lang/' . $language . '/lib/Installer/NotificationMailInstaller.php');
            self::assertStringContainsString('KK_PRICEWATCH_MAIL_SUBJECT', $template);
            self::assertStringContainsString('KK_PRICEWATCH_MAIL_BODY', $template);
        }
    }
}
