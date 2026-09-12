<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

interface RequestIdGeneratorInterface
{
    public function generate(): string;
}
