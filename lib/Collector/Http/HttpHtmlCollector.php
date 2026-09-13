<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Http;

use KK\PriceWatch\Collector\CollectorInterface;
use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorItemResult;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\CollectorResponse;
use Throwable;

final readonly class HttpHtmlCollector implements CollectorInterface
{
    public function __construct(
        private string $allowedHost,
        private HttpPriceExtractionOptions $options,
        private HttpTransportInterface $transport,
        private HtmlPriceExtractorInterface $extractor,
    ) {
    }

    public function collect(CollectorRequest $request): CollectorResponse
    {
        return CollectorResponse::success($request->requestId, array_map(
            fn(CollectorItem $item): CollectorItemResult => $this->collectItem($item),
            $request->items,
        ));
    }

    private function collectItem(CollectorItem $item): CollectorItemResult
    {
        if (!$this->isUrlAllowed($item->url)) {
            return CollectorItemResult::failure($item->id, 'URL_NOT_ALLOWED', 'The URL is not allowed for this competitor.');
        }
        try {
            $fetch = $this->transport->fetch($item->url);
        } catch (Throwable) {
            return CollectorItemResult::failure($item->id, 'HTTP_REQUEST_FAILED', 'The HTTP request failed.');
        }
        if (!$fetch->success) {
            return CollectorItemResult::failure($item->id, 'HTTP_REQUEST_FAILED', 'The HTTP request failed.');
        }
        if ($fetch->status < 200 || $fetch->status >= 300) {
            return CollectorItemResult::failure($item->id, 'HTTP_STATUS', 'The HTTP response status was not successful.');
        }
        if (!$this->isHtml($fetch->contentType)) {
            return CollectorItemResult::failure($item->id, 'INVALID_CONTENT_TYPE', 'The HTTP response is not HTML.');
        }
        try {
            $price = $this->extractor->extract($fetch->body, $this->options);
        } catch (HtmlPriceExtractionException $exception) {
            return CollectorItemResult::failure($item->id, $exception->errorCode, $exception->getMessage());
        } catch (Throwable) {
            return CollectorItemResult::failure($item->id, 'HTML_PARSE_ERROR', 'The HTML response could not be parsed.');
        }
        return CollectorItemResult::success($item->id, $price, $this->options->currency);
    }

    private function isUrlAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || filter_var($parts['host'], FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        return strcasecmp($parts['host'], $this->allowedHost) === 0;
    }

    private function isHtml(?string $contentType): bool
    {
        if ($contentType === null || trim($contentType) === '') {
            return false;
        }
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        return in_array($mediaType, ['text/html', 'application/xhtml+xml'], true);
    }
}
