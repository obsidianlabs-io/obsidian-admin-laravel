<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Resources;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actorRoleLevel = max(0, (int) $request->attributes->get('actorRoleLevel', 0));
        $role = $this->role;
        $roleCode = null;
        $roleName = null;
        $roleLevel = 0;

        if ($role instanceof Role) {
            $roleCode = $role->code;
            $roleName = $role->name;
            $roleLevel = max(0, (int) $role->level);
        }

        return [
            'id' => $this->id,
            'userName' => $this->name,
            'email' => $this->email,
            'roleCode' => $roleCode,
            'roleName' => $roleName,
            'roleLevel' => $roleLevel > 0 ? $roleLevel : null,
            'status' => in_array((string) $this->status, ['1', '2'], true) ? (string) $this->status : '1',
            'manageable' => $actorRoleLevel > 0 && $roleLevel < $actorRoleLevel,
            'version' => (string) ($this->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'createTime' => ApiDateTime::formatForRequest($this->created_at, $request),
            'updateTime' => ApiDateTime::formatForRequest($this->updated_at, $request),
        ];
    }
}
