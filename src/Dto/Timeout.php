<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `Timeout`: upstream timeouts in seconds.
 */
final readonly class Timeout implements Dto
{
    use DtoBehaviour;

    public const array KEYS = ['connect', 'send', 'read'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public int|float $connect,
        public int|float $send,
        public int|float $read,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            connect: $data->requiredNumber('connect'),
            send: $data->requiredNumber('send'),
            read: $data->requiredNumber('read'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'connect' => $this->connect,
            'send' => $this->send,
            'read' => $this->read,
        ];
    }
}
