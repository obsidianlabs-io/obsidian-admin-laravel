<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Domains\Access\Models\User;
use LogicException;

final readonly class TenantScopedContext
{
    private function __construct(
        private bool $ok,
        private string $code,
        private string $message,
        private ?User $user = null,
        private ?int $tenantId = null,
        private string $tenantName = '',
    ) {}

    public static function success(User $user, int $tenantId, string $tenantName = ''): self
    {
        return new self(
            ok: true,
            code: '0000',
            message: 'ok',
            user: $user,
            tenantId: $tenantId,
            tenantName: $tenantName,
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

    public function requireUser(): User
    {
        if (! $this->user instanceof User) {
            throw new LogicException('Tenant scoped context user is missing.');
        }

        return $this->user;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function tenantName(): string
    {
        return $this->tenantName;
    }
}
