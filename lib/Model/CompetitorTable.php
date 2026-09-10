<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

final class CompetitorTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_kk_pricewatch_competitor';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new StringField('NAME'))
                ->configureRequired()
                ->configureSize(255)
                ->configureValidation(static fn(): array => [
                    static fn(string $value): bool|string => trim($value) !== '' ?: 'Competitor name must not be blank.',
                    new LengthValidator(null, 255),
                ]),
            (new BooleanField('ACTIVE'))->configureValues('N', 'Y')->configureDefaultValue('Y'),
            (new IntegerField('SORT'))->configureDefaultValue(500),
            (new StringField('DOMAIN'))->configureNullable()->configureSize(255),
            (new StringField('COLLECTOR_TYPE'))
                ->configureRequired()
                ->configureSize(64)
                ->configureDefaultValue(CollectorType::MOCK)
                ->configureValidation(static fn(): array => [
                    static fn(string $value): bool|string => CollectorType::isValid($value) ?: 'Unknown collector type.',
                    new LengthValidator(null, 64),
                ]),
            (new StringField('COLLECTOR_HANDLER'))->configureNullable()->configureSize(512),
            (new TextField('COLLECTOR_OPTIONS'))
                ->configureRequired()
                ->configureDefaultValue('{}')
                ->configureValidation(static fn(): array => [static function (string $value): bool|string {
                    try {
                        CollectorOptions::decode($value);
                        return true;
                    } catch (\InvalidArgumentException) {
                        return 'Collector options must be a valid JSON object.';
                    }
                }]),
            (new DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn(): DateTime => new DateTime()),
            (new DatetimeField('UPDATED_AT'))->configureRequired()->configureDefaultValue(static fn(): DateTime => new DateTime()),
        ];
    }

    public static function onBeforeUpdate(Event $event): EventResult
    {
        $result = new EventResult();
        $result->modifyFields(['UPDATED_AT' => new DateTime()]);
        return $result;
    }
}
