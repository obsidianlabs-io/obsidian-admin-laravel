<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Support\ApiResultCode;

final readonly class OrganizationTeamBindingResult
{
    private function __construct(
        private bool $ok,
        private string $code,
        private string $message,
        private ?int $organizationId = null,
        private ?int $teamId = null,
    ) {}

    public static function success(?int $organizationId, ?int $teamId): self
    {
        return new self(
            ok: true,
            code: ApiResultCode::SUCCESS->value,
            message: 'ok',
            organizationId: $organizationId,
            teamId: $teamId,
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

    public function organizationId(): ?int
    {
        return $this->organizationId;
    }

    public function teamId(): ?int
    {
        return $this->teamId;
    }
}
