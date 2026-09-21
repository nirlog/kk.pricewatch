<?php
declare(strict_types=1);
namespace KK\PriceWatch\Collector\External;
use Bitrix\Main\Web\HttpClient;
use Throwable;
final class BitrixExternalCollectorTransport implements ExternalCollectorTransportInterface
{
    public const BODY_LIMIT = 2 * 1024 * 1024;
    public function post(string $endpoint, string $body, array $headers, int $connectTimeout, int $requestTimeout): ExternalCollectorHttpResult
    {
        try {
            $client = new HttpClient([
                'socketTimeout' => $connectTimeout,
                'streamTimeout' => $requestTimeout,
                'redirect' => false,
                'redirectMax' => 0,
                'sendEvents' => false,
            ]);
            $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
            $client->setPrivateIp(in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true));
            $client->setBodyLengthMax(self::BODY_LIMIT);
            foreach ($headers as $name => $value) $client->setHeader($name, $value);
            $response = $client->post($endpoint, $body);
            if (!is_string($response)) return ExternalCollectorHttpResult::failure($this->failureKind($client));
            if (strlen($response) > self::BODY_LIMIT) return ExternalCollectorHttpResult::failure('oversized');
            $type = $client->getHeaders()->get('Content-Type');
            return ExternalCollectorHttpResult::response((int) $client->getStatus(), is_string($type) ? $type : null, $response);
        } catch (Throwable) {
            return ExternalCollectorHttpResult::failure('network');
        }
    }
    private function failureKind(HttpClient $client): string
    {
        foreach ($client->getError() as $error) {
            $message = strtolower((string) $error);
            if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) return 'timeout';
            if (str_contains($message, 'maximum') && str_contains($message, 'length')) return 'oversized';
            if (str_contains($message, 'body') && str_contains($message, 'limit')) return 'oversized';
        }
        return 'network';
    }
}
