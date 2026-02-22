<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Resources;

use App\Domains\Tenant\Models\Tenant;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenantCode' => $this->code,
            'tenantName' => $this->name,
            'status' => (string) $this->status,
            'userCount' => (int) ($this->users_count ?? 0),
            'version' => (string) ($this->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'createTime' => ApiDateTime::formatForRequest($this->created_at, $request),
            'updateTime' => ApiDateTime::formatForRequest($this->updated_at, $request),
        ];
    }
}
