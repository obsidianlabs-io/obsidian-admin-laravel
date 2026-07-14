<?php

declare(strict_types=1);

namespace App\Domains\Shared\Events;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Contracts\AsyncAuditEvent;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Generic domain audit event for standard CRUD operations.
 *
 * Reads HTTP context (IP, User-Agent, Request-ID) from RequestContext
 * so Service methods don't need to accept a Request object.
 */
final class DomainAuditEvent implements AsyncAuditEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        private readonly string $actionName,
        private readonly string $auditableType,
        private readonly ?int $auditableId,
        private readonly ?int $userId,
        private readonly ?int $tenantId,
        private readonly ?array $oldValues = null,
        private readonly ?array $newValues = null,
        private readonly ?string $ipAddress = null,
        private readonly ?string $userAgent = null,
        private readonly ?string $requestId = null,
    ) {}

    /**
     * Build from a Model + User, reading HTTP context from RequestContext automatically.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function make(
        string $action,
        Model|string $auditable,
        User $actor,
        array $oldValues = [],
        array $newValues = [],
        ?int $tenantId = null,
    ): self {
        $auditableType = is_string($auditable) ? $auditable : $auditable::class;
        $auditableId = is_string($auditable) ? null : ($auditable->getKey() !== null ? (int) $auditable->getKey() : null);

        return new self(
            actionName: $action,
            auditableType: $auditableType,
            auditableId: $auditableId,
            userId: (int) $actor->id,
            tenantId: $tenantId ?? ($actor->tenant_id !== null ? (int) $actor->tenant_id : null),
            oldValues: $oldValues !== [] ? $oldValues : null,
            newValues: $newValues !== [] ? $newValues : null,
            ipAddress: RequestContext::ipAddress(),
            userAgent: RequestContext::userAgent(),
            requestId: RequestContext::requestId() !== '' ? RequestContext::requestId() : null,
        );
    }

    public function action(): string
    {
        return $this->actionName;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'request_id' => $this->requestId,
        ];
    }
}
