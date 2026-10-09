<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `UpstreamTLS`: TLS configuration for connecting to upstream nodes over HTTPS/gRPCS.
 */
final readonly class UpstreamTls implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'UpstreamTLS';

    public const array KEYS = ['client_cert_id', 'client_cert', 'client_key', 'verify', 'ca_certs'];

    /**
     * @param list<string>|null $caCerts
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string|int|null $clientCertId = null,
        public ?string $clientCert = null,
        public ?string $clientKey = null,
        public ?bool $verify = null,
        public ?array $caCerts = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            clientCertId: $data->id('client_cert_id'),
            clientCert: $data->string('client_cert'),
            clientKey: $data->string('client_key'),
            verify: $data->bool('verify'),
            caCerts: $data->stringList('ca_certs'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'client_cert_id' => $this->clientCertId,
            'client_cert' => $this->clientCert,
            'client_key' => $this->clientKey,
            'verify' => $this->verify,
            'ca_certs' => $this->caCerts,
        ];
    }
}
