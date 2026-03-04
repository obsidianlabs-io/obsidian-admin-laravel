<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

/**
 * @phpstan-type TenantOption array{tenantId: string, tenantName: string}
 */
final readonly class TenantContext
{
    /**
     * @param  list<TenantOption>  $tenants
     */
    private function __construct(
        private bool $ok,
        private string $code,
        private string $message,
        private ?int $tenantId = null,
        private string $tenantName = '',
        private array $tenants = [],
    ) {}

    /**
     * @param  list<TenantOption>  $tenants
     */
    public static function success(
        ?int $tenantId,
        string $tenantName,
        array $tenants = [],
        string $code = '0000',
        string $message = 'ok',
    ): self {
        return new self(
            ok: true,
            code: $code,
            message: $message,
            tenantId: $tenantId,
            tenantName: $tenantName,
            tenants: $tenants,
        );
    }

    public static function failure(string $code, string $message): self
    {
        return new self(
            ok: false,
            code: $code,
            message: $message,
        );
    }

    /**
     * @param  array{
     *   ok: bool,
     *   code: string,
     *   msg: string,
     *   tenantId?: int|null,
     *   tenantName?: string,
     *   tenants?: list<array{tenantId: string, tenantName: string}>
     * }  $payload
     */
    public static function fromPayload(array $payload): self
    {
        if ($payload['ok'] !== true) {
            return self::failure(
                $payload['code'],
                $payload['msg'],
            );
        }

        $tenants = $payload['tenants'] ?? [];

        return self::success(
            tenantId: is_int($payload['tenantId'] ?? null) ? $payload['tenantId'] : null,
            tenantName: (string) ($payload['tenantName'] ?? ''),
            tenants: $tenants,
            code: $payload['code'],
            message: $payload['msg'],
        );
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function tenantName(): string
    {
        return $this->tenantName;
    }

    /**
     * @return list<TenantOption>
     */
    public function tenants(): array
    {
        return $this->tenants;
    }
}
