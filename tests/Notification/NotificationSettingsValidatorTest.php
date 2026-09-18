<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Notification;

use KK\PriceWatch\Notification\NotificationSettingsValidator;
use PHPUnit\Framework\TestCase;

final class NotificationSettingsValidatorTest extends TestCase
{
    public function testEnabledConfigurationRequiresRecipients(): void
    {
        $errors = (new NotificationSettingsValidator())->validate(true, [], 's1', true, true);
        self::assertContains(NotificationSettingsValidator::RECIPIENTS_REQUIRED, $errors);
    }

    public function testEnabledConfigurationRequiresSite(): void
    {
        $errors = (new NotificationSettingsValidator())->validate(true, ['staff@example.test'], '', false, false);
        self::assertSame([NotificationSettingsValidator::SITE_REQUIRED], $errors);
    }

    public function testEnabledConfigurationRequiresActiveSiteAndTemplate(): void
    {
        $validator = new NotificationSettingsValidator();
        self::assertSame([NotificationSettingsValidator::SITE_INACTIVE],
            $validator->validate(true, ['staff@example.test'], 's1', false, false));
        self::assertSame([NotificationSettingsValidator::TEMPLATE_MISSING],
            $validator->validate(true, ['staff@example.test'], 's1', true, false));
        self::assertSame([], $validator->validate(true, ['staff@example.test'], 's1', true, true));
    }

    public function testDisabledConfigurationMayRemainIncomplete(): void
    {
        self::assertSame([], (new NotificationSettingsValidator())->validate(false, [], '', false, false));
    }
}
