<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Http;

interface HtmlPriceExtractorInterface
{
    /** @throws HtmlPriceExtractionException */
    public function extract(string $html, HttpPriceExtractionOptions $options): string;
}
