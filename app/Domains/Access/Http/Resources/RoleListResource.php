<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Resources;

use App\Domains\Access\Models\Role;
use App\Domains\Tenant\Models\Tenant;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
class RoleListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actorRoleLevel = max(0, (int) $request->attributes->get('actorRoleLevel', 0));
        $roleLevel = max(0, (int) ($this->level ?? 0));
        $tenant = $this->getRelationValue('tenant');
        $tenantName = $tenant instanceof Tenant ? (string) $tenant->name : 'Global (Superadmin)';

        return [
            'id' => $this->id,
            'roleCode' => $this->code,
            'roleName' => $this->name,
            'tenantId' => $this->tenant_id ? (string) $this->tenant_id : '',
            'tenantName' => $tenantName,
            'description' => (string) ($this->description ?? ''),
            'status' => (string) $this->status,
            'level' => $roleLevel,
            'manageable' => $roleLevel < $actorRoleLevel,
            'userCount' => (int) ($this->users_count ?? 0),
            'version' => (string) ($this->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'permissionCodes' => $this->permissions
                ->where('status', '1')
                ->pluck('code')
                ->map(static fn ($code): string => (string) $code)
                ->values()
                ->all(),
            'createTime' => ApiDateTime::formatForRequest($this->created_at, $request),
            'updateTime' => ApiDateTime::formatForRequest($this->updated_at, $request),
        ];
    }
}
