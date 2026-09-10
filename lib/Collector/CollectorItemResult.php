<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

use InvalidArgumentException;

final readonly class CollectorItemResult
{
    private function __construct(
        public string $id,
        public bool $success,
        public ?string $price,
        public ?string $currency,
        public ?CollectorError $error,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Result item ID must not be empty.');
        }
    }

    public static function success(string $id, string $price, string $currency): self
    {
        Money::assertPrice($price);
        Money::assertCurrency($currency);

        return new self($id, true, $price, $currency, null);
    }

    public static function failure(string $id, string $code, string $message): self
    {
        return new self($id, false, null, null, new CollectorError($code, $message));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->success) {
            return ['id' => $this->id, 'success' => true, 'price' => $this->price, 'currency' => $this->currency];
        }

        return ['id' => $this->id, 'success' => false, 'error' => $this->error?->toArray()];
    }
}
