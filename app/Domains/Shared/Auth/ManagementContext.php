<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Domains\Access\Models\User;
use App\Support\ApiResultCode;
use LogicException;

final readonly class ManagementContext
{
    private function __construct(
        private bool $ok,
        private string $code,
        private string $message,
        private ?User $user = null,
        private ?int $actorLevel = null,
        private ?int $tenantId = null,
        private bool $isSuper = false
    ) {}

    public static function success(
        User $user,
        int $actorLevel,
        ?int $tenantId,
        bool $isSuper = false
    ): self {
        return new self(
            ok: true,
            code: ApiResultCode::SUCCESS->value,
            message: 'ok',
            user: $user,
            actorLevel: $actorLevel,
            tenantId: $tenantId,
            isSuper: $isSuper
        );
    }

    public static function failure(string|ApiResultCode $code, string $message): self
    {
        return new self(
            ok: false,
            code: $code instanceof ApiResultCode ? $code->value : $code,
            message: $message
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
            throw new LogicException('Management context user is missing.');
        }

        return $this->user;
    }

    public function actorLevel(): int
    {
        return max(0, (int) ($this->actorLevel ?? 0));
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
