<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Support\ApiResultCode;

final readonly class TenantContext
{
    /**
     * @param  list<TenantOptionData>  $tenants
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
     * @param  list<TenantOptionData>  $tenants
     */
    public static function success(
        ?int $tenantId,
        string $tenantName,
        array $tenants = [],
        string|ApiResultCode $code = ApiResultCode::SUCCESS,
        string $message = 'ok',
    ): self {
        return new self(
            ok: true,
            code: $code instanceof ApiResultCode ? $code->value : $code,
            message: $message,
            tenantId: $tenantId,
            tenantName: $tenantName,
            tenants: $tenants,
        );
    }

    public static function failure(string|ApiResultCode $code, string $message): self
    {
        return new self(
            ok: false,
            code: $code instanceof ApiResultCode ? $code->value : $code,
            message: $message,
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
     * @return list<TenantOptionData>
     */
    public function tenants(): array
    {
        return $this->tenants;
    }
}
