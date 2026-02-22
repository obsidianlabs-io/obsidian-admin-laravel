<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant;

use App\DTOs\Tenant\UpdateTenantDTO;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->route('id');

        return [
            'tenantCode' => ['required', 'string', 'max:64', Rule::unique('tenants', 'code')->ignore($tenantId)],
            'tenantName' => ['required', 'string', 'max:120', Rule::unique('tenants', 'name')->ignore($tenantId)],
            'status' => ['nullable', 'in:1,2'],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toDTO(): UpdateTenantDTO
    {
        $validated = $this->validated();
        $status = $validated['status'] ?? null;

        return new UpdateTenantDTO(
            tenantCode: trim((string) $validated['tenantCode']),
            tenantName: trim((string) $validated['tenantName']),
            status: $status !== null ? (string) $status : null,
        );
    }
}
