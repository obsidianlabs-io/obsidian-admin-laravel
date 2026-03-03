<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Resources;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Domains\Tenant\Models\Tenant;
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
        $user = $this->getRelationValue('user');
        $tenant = $this->getRelationValue('tenant');
        $userName = $user instanceof User ? (string) $user->name : 'System';
        $tenantName = $tenant instanceof Tenant ? (string) $tenant->name : 'No Tenant';
        $oldValues = (array) ($this->old_values ?? []);
        $newValues = (array) ($this->new_values ?? []);

        return [
            'id' => $this->id,
            'action' => (string) $this->action,
            'userName' => $userName,
            'tenantId' => $this->tenant_id ? (string) $this->tenant_id : '',
            'tenantName' => $tenantName,
            'auditableType' => $auditableType,
            'auditableId' => $this->auditable_id ? (string) $this->auditable_id : '',
            'target' => $this->auditable_id ? "{$auditableType}#{$this->auditable_id}" : $auditableType,
            'oldValues' => $oldValues,
            'newValues' => $newValues,
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
