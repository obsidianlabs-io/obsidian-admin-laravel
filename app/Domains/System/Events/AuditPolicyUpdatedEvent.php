<?php

declare(strict_types=1);

namespace App\Domains\System\Events;

use App\Domains\Access\Models\User;
use App\Domains\System\Contracts\AsyncAuditEvent;
use App\Domains\System\Data\AuditPolicyGlobalUpdateResultData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

final class AuditPolicyUpdatedEvent implements AsyncAuditEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<array{
     *   action: string,
     *   old: array{enabled: bool, samplingRate: float, retentionDays: int},
     *   new: array{enabled: bool, samplingRate: float, retentionDays: int}
     * }>  $changes
     */
    public function __construct(
        private readonly int $changedByUserId,
        private readonly string $changeReason,
        private readonly int $updated,
        private readonly int $clearedTenantOverrides,
        private readonly string $revisionId,
        private readonly array $changes,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly ?string $requestId
    ) {}

    public static function fromRequest(
        User $user,
        Request $request,
        string $changeReason,
        AuditPolicyGlobalUpdateResultData $result
    ): self {
        $requestId = trim((string) ($request->attributes->get('request_id', '') ?? ''));

        return new self(
            changedByUserId: (int) $user->id,
            changeReason: $changeReason,
            updated: $result->updated,
            clearedTenantOverrides: $result->clearedTenantOverrides,
            revisionId: $result->revisionId,
            changes: $result->changesToArray(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: $requestId !== '' ? $requestId : null
        );
    }

    public function action(): string
    {
        return 'audit.policy.update';
    }

    public function tenantId(): ?int
    {
        return null;
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->changedByUserId,
            'auditable_type' => 'system',
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'scopeType' => 'global',
                'updated' => $this->updated,
                'clearedTenantOverrides' => $this->clearedTenantOverrides,
                'revisionId' => $this->revisionId,
                'changeReason' => $this->changeReason,
                'changes' => $this->changes,
            ],
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'request_id' => $this->requestId,
        ];
    }
}
