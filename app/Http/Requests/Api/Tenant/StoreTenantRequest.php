<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant;

use App\DTOs\Tenant\CreateTenantDTO;
use App\Http\Requests\Api\BaseApiRequest;

class StoreTenantRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tenantCode' => ['required', 'string', 'max:64', 'unique:tenants,code'],
            'tenantName' => ['required', 'string', 'max:120', 'unique:tenants,name'],
            'status' => ['nullable', 'in:1,2'],
        ];
    }

    public function toDTO(): CreateTenantDTO
    {
        $validated = $this->validated();

        return new CreateTenantDTO(
            tenantCode: trim((string) $validated['tenantCode']),
            tenantName: trim((string) $validated['tenantName']),
            status: (string) ($validated['status'] ?? '1'),
        );
    }
}
