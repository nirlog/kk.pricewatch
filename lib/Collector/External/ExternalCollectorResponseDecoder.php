<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\External;

use InvalidArgumentException;
use JsonException;
use KK\PriceWatch\Collector\CollectorItemResult;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\CollectorResponse;
use Throwable;

final class ExternalCollectorResponseDecoder
{
    public function decode(string $json): CollectorResponse
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_is_list($data)) throw new InvalidArgumentException();
            if (($data['schema_version'] ?? null) !== CollectorRequest::SCHEMA_VERSION
                || !is_string($data['request_id'] ?? null) || trim($data['request_id']) === ''
                || !is_bool($data['success'] ?? null) || !is_array($data['items'] ?? null) || !array_is_list($data['items'])) {
                throw new InvalidArgumentException();
            }
            if ($data['success'] === false) {
                if ($data['items'] !== [] || !isset($data['error'])) throw new InvalidArgumentException();
                [$code, $message] = $this->error($data['error']);
                return CollectorResponse::failure($data['request_id'], $code, $message);
            }
            if (array_key_exists('error', $data)) throw new InvalidArgumentException();
            $items = [];
            $seen = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item) || array_is_list($item) || !is_string($item['id'] ?? null) || trim($item['id']) === ''
                    || !is_bool($item['success'] ?? null) || isset($seen[$item['id']])) throw new InvalidArgumentException();
                $seen[$item['id']] = true;
                if ($item['success']) {
                    if (!is_string($item['price'] ?? null) || !is_string($item['currency'] ?? null) || array_key_exists('error', $item)) throw new InvalidArgumentException();
                    $items[] = CollectorItemResult::success($item['id'], $item['price'], $item['currency']);
                } else {
                    if (!array_key_exists('error', $item) || array_key_exists('price', $item) || array_key_exists('currency', $item)) throw new InvalidArgumentException();
                    [$code, $message] = $this->error($item['error']);
                    $items[] = CollectorItemResult::failure($item['id'], $code, $message);
                }
            }
            return CollectorResponse::success($data['request_id'], $items);
        } catch (JsonException|InvalidArgumentException $exception) {
            throw new InvalidArgumentException('External collector response violates contract 1.0.', 0, $exception);
        }
    }

    /** @return array{string, string} */
    private function error(mixed $error): array
    {
        if (!is_array($error) || array_is_list($error) || !is_string($error['code'] ?? null)
            || preg_match('/^[A-Z0-9_]{1,64}$/D', $error['code']) !== 1
            || !is_string($error['message'] ?? null) || trim($error['message']) === '' || strlen($error['message']) > 4096) {
            throw new InvalidArgumentException();
        }
        return [$error['code'], $error['message']];
    }
}
