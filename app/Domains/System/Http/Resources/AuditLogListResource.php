<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Resources;

use App\Domains\System\Models\AuditLog;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $auditableType = $this->resolveAuditableType((string) $this->auditable_type);

        return [
            'id' => $this->id,
            'action' => (string) $this->action,
            'userName' => (string) ($this->user?->name ?? 'System'),
            'tenantId' => $this->tenant_id ? (string) $this->tenant_id : '',
            'tenantName' => $this->tenant?->name ?? 'No Tenant',
            'auditableType' => $auditableType,
            'auditableId' => $this->auditable_id ? (string) $this->auditable_id : '',
            'target' => $this->auditable_id ? "{$auditableType}#{$this->auditable_id}" : $auditableType,
            'oldValues' => is_array($this->old_values) ? $this->old_values : [],
            'newValues' => is_array($this->new_values) ? $this->new_values : [],
            'ipAddress' => (string) ($this->ip_address ?? ''),
            'userAgent' => (string) ($this->user_agent ?? ''),
            'requestId' => (string) ($this->request_id ?? ''),
            'createTime' => ApiDateTime::formatForRequest($this->created_at, $request),
        ];
    }

    private function resolveAuditableType(string $auditableType): string
    {
        $trimmed = trim($auditableType);
        if ($trimmed === '') {
            return '';
        }

        $parts = explode('\\', $trimmed);

        return (string) end($parts);
    }
}
