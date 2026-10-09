<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Internal;

use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnexpectedResponseException;

/**
 * Decoded Admin API response. Never exposed through the public API.
 *
 * @internal
 */
final readonly class Response
{
    /**
     * @param array<string, list<string>> $headers lower-cased names
     * @param mixed $body decoded JSON, the raw string for non-JSON bodies, or null when empty
     */
    public function __construct(
        public string $method,
        public string $uri,
        public int $status,
        public array $headers,
        public mixed $body,
        public string $raw,
    ) {}

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * The body as a JSON object.
     *
     * @return array<string, mixed>
     */
    public function object(): array
    {
        if (! is_array($this->body) || ($this->body !== [] && array_is_list($this->body))) {
            throw $this->unexpected('expected a JSON object body');
        }

        $out = [];
        foreach ($this->body as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    public function unexpected(string $reason): UnexpectedResponseException
    {
        return new UnexpectedResponseException($this->method, $this->uri, $this->status, $this->raw, $reason);
    }
}
