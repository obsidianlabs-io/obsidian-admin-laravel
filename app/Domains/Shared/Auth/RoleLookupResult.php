<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Domains\Access\Models\Role;
use App\Support\ApiResultCode;
use LogicException;

final readonly class RoleLookupResult
{
    private function __construct(
        private bool $ok,
        private string $code,
        private string $message,
        private ?Role $role = null,
    ) {}

    public static function success(Role $role): self
    {
        return new self(
            ok: true,
            code: ApiResultCode::SUCCESS->value,
            message: 'ok',
            role: $role,
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

    public function role(): ?Role
    {
        return $this->role;
    }

    public function requireRole(): Role
    {
        if (! $this->role instanceof Role) {
            throw new LogicException('Role lookup result role is missing.');
        }

        return $this->role;
    }
}
