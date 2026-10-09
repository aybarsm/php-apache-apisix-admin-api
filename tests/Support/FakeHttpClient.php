<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * PSR-18 client that replays queued responses and records requests.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|Throwable> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    public function push(ResponseInterface|Throwable ...$items): self
    {
        array_push($this->queue, ...$items);

        return $this;
    }

    /**
     * @param array<string, string> $headers
     */
    public function json(int $status, mixed $body, array $headers = []): self
    {
        return $this->push(new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }

    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? throw new RuntimeException(sprintf('No queued response for %s %s', $request->getMethod(), $request->getUri()));

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)] ?? throw new RuntimeException('No requests recorded');
    }

    /**
     * Decoded JSON body of the last request.
     */
    public function lastJson(): mixed
    {
        return json_decode((string) $this->last()->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Path + query of the last request.
     */
    public function lastTarget(): string
    {
        $uri = $this->last()->getUri();
        $query = $uri->getQuery();

        return $uri->getPath().($query === '' ? '' : '?'.$query);
    }
}
