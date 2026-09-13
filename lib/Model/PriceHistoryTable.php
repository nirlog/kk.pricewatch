<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\DecimalField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use KK\PriceWatch\Collector\Money;

/** Immutable price-change snapshots. Application code must only add rows. */
final class PriceHistoryTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_kk_pricewatch_price_history';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new IntegerField('PRODUCT_COMPETITOR_ID'))->configureRequired()->addValidator(self::positiveIdValidator(...)),
            (new IntegerField('PRODUCT_ID'))->configureRequired()->addValidator(self::positiveIdValidator(...)),
            (new IntegerField('COMPETITOR_ID'))->configureRequired()->addValidator(self::positiveIdValidator(...)),
            (new TextField('URL'))->configureRequired()
                ->addValidator(static fn(string $value): bool|string => $value !== '' ?: 'URL snapshot must not be empty.'),
            (new StringField('URL_HASH'))->configureRequired()->configureSize(64)
                ->addValidator(new LengthValidator(64, 64))
                ->addValidator(static fn(string $value): bool|string => preg_match('/^[a-f0-9]{64}$/D', $value) === 1
                    ?: 'URL hash must be a lowercase SHA-256 value.'),
            (new DecimalField('PRICE'))->configureRequired()->configurePrecision(18)->configureScale(2),
            (new StringField('CURRENCY'))->configureRequired()->configureSize(3)
                ->addValidator(new LengthValidator(3, 3))->addValidator(self::currencyValidator(...)),
            (new DatetimeField('COLLECTED_AT'))->configureRequired(),
        ];
    }

    private static function positiveIdValidator(int $value): bool|string
    {
        return $value > 0 ?: 'ID must be greater than zero.';
    }

    private static function currencyValidator(string $value): bool|string
    {
        try {
            Money::assertCurrency($value);
            return true;
        } catch (\InvalidArgumentException) {
            return 'Currency must be an uppercase three-letter code.';
        }
    }
}
