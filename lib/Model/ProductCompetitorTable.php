<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\DecimalField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use KK\PriceWatch\Collector\Money;

final class ProductCompetitorTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_kk_pricewatch_product_competitor';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new IntegerField('PRODUCT_ID'))
                ->configureRequired()
                ->addValidator(self::positiveIdValidator(...)),
            (new IntegerField('COMPETITOR_ID'))
                ->configureRequired()
                ->addValidator(self::positiveIdValidator(...)),
            (new TextField('URL'))
                ->configureRequired()
                ->addValidator(static fn(string $value): bool|string => trim($value) !== '' ?: 'Product URL must not be blank.'),
            (new StringField('URL_HASH'))
                ->configureRequired()
                ->configureSize(64)
                ->addValidator(static fn(string $value): bool|string => preg_match('/^[a-f0-9]{64}$/D', $value) === 1
                    ?: 'URL hash must be a lowercase SHA-256 value.')
                ->addValidator(new LengthValidator(64, 64)),
            (new BooleanField('ACTIVE'))->configureValues('N', 'Y')->configureDefaultValue('Y'),
            (new DecimalField('CURRENT_PRICE'))->configureNullable()->configurePrecision(18)->configureScale(2),
            (new StringField('CURRENCY'))
                ->configureNullable()
                ->configureSize(3)
                ->addValidator(static function (string $value): bool|string {
                    try {
                        Money::assertCurrency($value);
                        return true;
                    } catch (\InvalidArgumentException) {
                        return 'Currency must be an uppercase three-letter code.';
                    }
                }),
            (new StringField('STATUS'))
                ->configureRequired()
                ->configureSize(16)
                ->configureDefaultValue(CollectionStatus::NEW)
                ->addValidator(static fn(string $value): bool|string => CollectionStatus::isValid($value) ?: 'Unknown collection status.'),
            (new StringField('ERROR_CODE'))->configureNullable()->configureSize(64)
                ->addValidator(new LengthValidator(null, 64)),
            (new TextField('ERROR_MESSAGE'))->configureNullable(),
            (new DatetimeField('LAST_CHECK_AT'))->configureNullable(),
            (new DatetimeField('LAST_SUCCESS_AT'))->configureNullable(),
            (new DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn(): DateTime => new DateTime()),
            (new DatetimeField('UPDATED_AT'))->configureRequired()->configureDefaultValue(static fn(): DateTime => new DateTime()),
            (new Reference('COMPETITOR', CompetitorTable::class, Join::on('this.COMPETITOR_ID', 'ref.ID'))),
        ];
    }

    public static function onBeforeAdd(Event $event): EventResult
    {
        $result = new EventResult();
        $fields = $event->getParameter('fields');

        if (array_key_exists('URL', $fields)) {
            $result->modifyFields(['URL_HASH' => ProductUrl::hash((string) $fields['URL'])]);
        }

        return $result;
    }

    public static function onBeforeUpdate(Event $event): EventResult
    {
        $result = new EventResult();
        $fields = $event->getParameter('fields');
        $changes = ['UPDATED_AT' => new DateTime()];

        if (array_key_exists('URL', $fields)) {
            $changes['URL_HASH'] = ProductUrl::hash((string) $fields['URL']);
        } elseif (array_key_exists('URL_HASH', $fields)) {
            // URL_HASH is derived only: ignore attempts to alter it independently.
            $result->unsetFields(['URL_HASH']);
        }

        $result->modifyFields($changes);
        return $result;
    }

    private static function positiveIdValidator(int $value): bool|string
    {
        return $value > 0 ?: 'ID must be greater than zero.';
    }
}
