<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\External;

use JsonException;
use KK\PriceWatch\Collector\CollectorInterface;
use KK\PriceWatch\Collector\CollectorItemResult;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\CollectorResponse;
use Throwable;

final readonly class ExternalCollector implements CollectorInterface
{
    public function __construct(
        private string $endpoint,
        private string $token,
        private int $connectTimeout,
        private int $requestTimeout,
        private ExternalCollectorTransportInterface $transport,
        private ExternalCollectorResponseDecoder $decoder,
    ) {}

    public function collect(CollectorRequest $request): CollectorResponse
    {
        try {
            $payload = $request->toArray();
            $payload['options'] = (object) $payload['options'];
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $result = $this->transport->post($this->endpoint, $body, [
                'Content-Type' => 'application/json', 'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ], $this->connectTimeout, $this->requestTimeout);
        } catch (Throwable) {
            return $this->failure($request, 'COLLECTOR_ERROR', 'External collector request failed.');
        }
        if (!$result->success) {
            if ($result->failure === 'timeout') return $this->failure($request, 'COLLECTOR_TIMEOUT', 'External collector request timed out.');
            if ($result->failure === 'oversized') return $this->invalid($request);
            return $this->failure($request, 'COLLECTOR_ERROR', 'External collector request failed.');
        }
        if ($result->status < 200 || $result->status >= 300) return $this->failure($request, 'COLLECTOR_ERROR', 'External collector request failed.');
        if (!$this->isJson($result->contentType) || strlen($result->body) > BitrixExternalCollectorTransport::BODY_LIMIT) return $this->invalid($request);
        try {
            return $this->sanitizeRemoteErrors($this->decoder->decode($result->body));
        } catch (Throwable) {
            return $this->invalid($request);
        }
    }

    private function sanitizeRemoteErrors(CollectorResponse $response): CollectorResponse
    {
        if ($this->token === '') {
            return $response;
        }
        if (!$response->success) {
            return CollectorResponse::failure(
                $response->requestId,
                $response->error->code,
                str_replace($this->token, '[REDACTED]', $response->error->message),
            );
        }

        return CollectorResponse::success($response->requestId, array_map(
            fn (CollectorItemResult $item): CollectorItemResult => $item->success
                ? $item
                : CollectorItemResult::failure(
                    $item->id,
                    $item->error->code,
                    str_replace($this->token, '[REDACTED]', $item->error->message),
                ),
            $response->items,
        ));
    }

    /** @return array{endpoint: string, tokenConfigured: bool, connectTimeout: int, requestTimeout: int, transport: class-string, decoder: class-string} */
    public function __debugInfo(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'tokenConfigured' => $this->token !== '',
            'connectTimeout' => $this->connectTimeout,
            'requestTimeout' => $this->requestTimeout,
            'transport' => $this->transport::class,
            'decoder' => $this->decoder::class,
        ];
    }

    private function isJson(?string $contentType): bool
    {
        if (!is_string($contentType)) return false;
        $type = strtolower(trim(explode(';', $contentType, 2)[0]));
        return $type === 'application/json' || preg_match('~^application/[a-z0-9.!#$&^_+-]+\+json$~D', $type) === 1;
    }
    private function invalid(CollectorRequest $request): CollectorResponse { return $this->failure($request, 'INVALID_RESPONSE', 'External collector returned an invalid response.'); }
    private function failure(CollectorRequest $request, string $code, string $message): CollectorResponse { return CollectorResponse::failure($request->requestId, $code, $message); }
}
