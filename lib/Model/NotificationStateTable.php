<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

final class NotificationStateTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_kk_pricewatch_notification_state';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new IntegerField('PRODUCT_COMPETITOR_ID'))->configureRequired()
                ->addValidator(static fn(int $value): bool|string => $value > 0 ?: 'ID must be greater than zero.'),
            (new StringField('RULE_CODE'))->configureRequired()->configureSize(32)
                ->addValidator(new LengthValidator(1, 32)),
            (new BooleanField('NOTIFIED_ACTIVE'))->configureRequired()->configureValues('N', 'Y')->configureDefaultValue('N'),
            (new StringField('NOTIFIED_FINGERPRINT'))->configureNullable()->configureSize(64)
                ->addValidator(new LengthValidator(null, 64)),
            (new DatetimeField('LAST_NOTIFIED_AT'))->configureNullable(),
            (new DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn(): DateTime => new DateTime()),
            (new DatetimeField('UPDATED_AT'))->configureRequired()->configureDefaultValue(static fn(): DateTime => new DateTime()),
        ];
    }
}
