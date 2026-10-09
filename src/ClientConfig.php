<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi;

use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;

/**
 * Connection settings for the Admin API.
 *
 * `timeout` and `connectTimeout` (seconds) only apply to the default Guzzle
 * client; configure your own PSR-18 client when you inject one.
 */
final readonly class ClientConfig
{
    public const string DEFAULT_BASE_URI = 'http://127.0.0.1:9180';

    public const float DEFAULT_TIMEOUT = 30.0;

    public const float DEFAULT_CONNECT_TIMEOUT = 5.0;

    public const string DEFAULT_USER_AGENT = 'aybarsm/apache-apisix-admin-api';

    public string $baseUri;

    /**
     * @param array<string, string> $headers extra headers sent with every request
     */
    public function __construct(
        string $baseUri = self::DEFAULT_BASE_URI,
        #[\SensitiveParameter]
        public string $apiKey = '',
        public float $timeout = self::DEFAULT_TIMEOUT,
        public float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        public string $userAgent = self::DEFAULT_USER_AGENT,
        public array $headers = [],
    ) {
        $baseUri = rtrim(trim($baseUri), '/');
        $scheme = parse_url($baseUri, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true) || parse_url($baseUri, PHP_URL_HOST) === null) {
            throw new InvalidArgumentException(sprintf('Base URI must be an absolute http(s) URL, got "%s".', $baseUri));
        }
        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new InvalidArgumentException('Timeouts must be positive.');
        }

        $this->baseUri = $baseUri;
    }

    public function __debugInfo(): array
    {
        return [
            'baseUri' => $this->baseUri,
            'apiKey' => $this->apiKey === '' ? '' : '***',
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'userAgent' => $this->userAgent,
            'headers' => array_keys($this->headers),
        ];
    }
}
