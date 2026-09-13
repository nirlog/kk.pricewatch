<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Http;

use DOMDocument;
use DOMXPath;

final class DomXPathPriceExtractor implements HtmlPriceExtractorInterface
{
    public function extract(string $html, HttpPriceExtractionOptions $options): string
    {
        if ($html === '') {
            throw new HtmlPriceExtractionException('HTML_PARSE_ERROR', 'The HTML response could not be parsed.');
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            // DOMDocument otherwise treats HTML without a charset declaration as ISO-8859-1.
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new HtmlPriceExtractionException('HTML_PARSE_ERROR', 'The HTML response could not be parsed.');
        }
        $nodes = (new DOMXPath($document))->query($options->xpath);
        if ($nodes === false) {
            throw new HtmlPriceExtractionException('HTML_PARSE_ERROR', 'The configured XPath could not be evaluated.');
        }
        if ($nodes->length === 0) {
            throw new HtmlPriceExtractionException('PRICE_NOT_FOUND', 'The configured selector did not find a price.');
        }

        $prices = [];
        foreach ($nodes as $node) {
            $prices[$this->normalizePrice($node->textContent)] = true;
        }
        if (count($prices) !== 1) {
            throw new HtmlPriceExtractionException('PRICE_AMBIGUOUS', 'The configured selector returned incompatible prices.');
        }
        return array_key_first($prices);
    }

    private function normalizePrice(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/(?<![\d.,])(?:\d{1,3}(?:[ \x{00A0}\x{202F}]\d{3})+|\d+)(?:[.,]\d+)?(?![\d.,])/u', $text, $matches);
        if (count($matches[0]) !== 1) {
            throw new HtmlPriceExtractionException('PRICE_INVALID', 'The selected value is not a valid unambiguous price.');
        }
        $number = str_replace([" ", "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], $matches[0][0]);
        [$whole, $fraction] = array_pad(explode('.', $number, 2), 2, '');
        if ($fraction !== '' && strlen($fraction) > 2) {
            throw new HtmlPriceExtractionException('PRICE_INVALID', 'The selected price has unsupported precision.');
        }
        $whole = ltrim($whole, '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($fraction, 2, '0');
    }
}
