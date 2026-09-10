<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use InvalidArgumentException;
use JsonException;

final class CollectorOptions
{
    /** @param array<string, mixed> $options */
    public static function encode(array $options): string
    {
        if (array_is_list($options) && $options !== []) {
            throw new InvalidArgumentException('Collector options must be a JSON object.');
        }

        try {
            return json_encode(
                $options === [] ? (object) [] : $options,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Collector options cannot be encoded as JSON.', 0, $exception);
        }
    }

    /** @return array<string, mixed> */
    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        try {
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Collector options contain invalid JSON.', 0, $exception);
        }

        if (!$object instanceof \stdClass || !is_array($decoded)) {
            throw new InvalidArgumentException('Collector options must be a JSON object.');
        }

        return $decoded;
    }
}
