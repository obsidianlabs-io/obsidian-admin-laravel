<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Support\ApiResultCode;

final readonly class RoleScopeContext
{
    private function __construct(
        private bool $ok,
        private string $code,
        private string $message,
        private ?int $tenantId = null,
        private bool $isSuper = false,
    ) {}

    public static function success(?int $tenantId, bool $isSuper): self
    {
        return new self(
            ok: true,
            code: ApiResultCode::SUCCESS->value,
            message: 'ok',
            tenantId: $tenantId,
            isSuper: $isSuper,
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

    public function isSuper(): bool
    {
        return $this->isSuper;
    }
}
