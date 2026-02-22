<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Jobs\WriteAuditLogJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    public function __construct(private readonly AuditPolicyService $auditPolicyService) {}

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $action,
        Model|string $auditable,
        ?User $actor = null,
        ?Request $request = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $tenantId = null
    ): void {
        $effectiveTenantId = $tenantId ?? ($actor?->tenant_id ? (int) $actor->tenant_id : null);
        if (! $this->auditPolicyService->shouldLog($action, $effectiveTenantId)) {
            return;
        }

        $auditableType = is_string($auditable) ? $auditable : $auditable::class;
        $auditableId = is_string($auditable) ? null : (int) ($auditable->getKey() ?? 0);
        $payload = $this->normalizePayload([
            'user_id' => $actor?->id,
            'tenant_id' => $effectiveTenantId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId > 0 ? $auditableId : null,
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => trim((string) ($request?->attributes->get('request_id', '') ?? '')) ?: null,
        ]);

        if ((bool) config('audit.queue.enabled', true)) {
            try {
                dispatch(new WriteAuditLogJob($payload));

                return;
            } catch (Throwable $exception) {
                Log::warning('audit.dispatch_failed_fallback_to_sync', [
                    'action' => $action,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->writeAuditLogPayload($payload);
    }

    /**
     * @param  array{
     *   user_id: int|null,
     *   auditable_type: string,
     *   auditable_id: int|null,
     *   old_values: array<string, mixed>|null,
     *   new_values: array<string, mixed>|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   request_id: string|null
     * }  $payload
     */
    public function recordPreparedPayload(string $action, array $payload, ?int $tenantId): void
    {
        if (! $this->auditPolicyService->shouldLog($action, $tenantId)) {
            return;
        }

        $this->writeAuditLogPayload($this->normalizePayload([
            'user_id' => $payload['user_id'],
            'tenant_id' => $tenantId,
            'action' => $action,
            'auditable_type' => (string) $payload['auditable_type'],
            'auditable_id' => $payload['auditable_id'],
            'old_values' => $payload['old_values'],
            'new_values' => $payload['new_values'],
            'ip_address' => $payload['ip_address'],
            'user_agent' => $payload['user_agent'],
            'request_id' => $payload['request_id'],
        ]));
    }

    /**
     * @param  array{
     *   user_id: int|null,
     *   tenant_id: int|null,
     *   action: string,
     *   auditable_type: string,
     *   auditable_id: int|null,
     *   old_values: array<string, mixed>|null,
     *   new_values: array<string, mixed>|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   request_id: string|null
     * }  $payload
     */
    public function writeAuditLogPayload(array $payload): void
    {
        AuditLog::query()->create($payload);
    }

    /**
     * @param  array{
     *   user_id: int|null,
     *   tenant_id: int|null,
     *   action: string,
     *   auditable_type: string,
     *   auditable_id: int|null,
     *   old_values: array<string, mixed>|null,
     *   new_values: array<string, mixed>|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   request_id: string|null
     * }  $payload
     * @return array{
     *   user_id: int|null,
     *   tenant_id: int|null,
     *   action: string,
     *   auditable_type: string,
     *   auditable_id: int|null,
     *   old_values: array<string, mixed>|null,
     *   new_values: array<string, mixed>|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   request_id: string|null
     * }
     */
    private function normalizePayload(array $payload): array
    {
        return [
            'user_id' => is_numeric($payload['user_id']) ? (int) $payload['user_id'] : null,
            'tenant_id' => is_numeric($payload['tenant_id']) ? (int) $payload['tenant_id'] : null,
            'action' => trim((string) $payload['action']),
            'auditable_type' => trim((string) $payload['auditable_type']),
            'auditable_id' => is_numeric($payload['auditable_id']) ? (int) $payload['auditable_id'] : null,
            'old_values' => is_array($payload['old_values']) ? $payload['old_values'] : null,
            'new_values' => is_array($payload['new_values']) ? $payload['new_values'] : null,
            'ip_address' => ($payload['ip_address'] ?? null) !== null ? trim((string) $payload['ip_address']) : null,
            'user_agent' => ($payload['user_agent'] ?? null) !== null ? trim((string) $payload['user_agent']) : null,
            'request_id' => ($payload['request_id'] ?? null) !== null ? trim((string) $payload['request_id']) : null,
        ];
    }
}
