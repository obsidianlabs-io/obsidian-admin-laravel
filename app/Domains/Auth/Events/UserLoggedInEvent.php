<?php

declare(strict_types=1);

namespace App\Domains\Auth\Events;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Contracts\AsyncAuditEvent;
use App\Support\RequestContext;
use Illuminate\Foundation\Events\Dispatchable;

final class UserLoggedInEvent implements AsyncAuditEvent
{
    use Dispatchable;

    public function __construct(
        private readonly int $userId,
        private readonly ?int $tenantId,
        private readonly bool $rememberMe,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly ?string $requestId
    ) {}

    public static function make(User $user, bool $rememberMe): self
    {
        return new self(
            userId: (int) $user->id,
            tenantId: $user->tenant_id ? (int) $user->tenant_id : null,
            rememberMe: $rememberMe,
            ipAddress: RequestContext::ipAddress(),
            userAgent: RequestContext::userAgent(),
            requestId: RequestContext::requestId()
        );
    }

    public function action(): string
    {
        return 'auth.login';
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'auditable_type' => User::class,
            'auditable_id' => $this->userId,
            'old_values' => null,
            'new_values' => [
                'rememberMe' => $this->rememberMe,
            ],
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'request_id' => $this->requestId,
        ];
    }
}
