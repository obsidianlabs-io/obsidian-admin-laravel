<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Permission;

use App\DTOs\Permission\CreatePermissionDTO;
use App\Http\Requests\Api\BaseApiRequest;

class StorePermissionRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'permissionCode' => ['required', 'string', 'max:100', 'unique:permissions,code'],
            'permissionName' => ['required', 'string', 'max:100', 'unique:permissions,name'],
            'group' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:1,2'],
        ];
    }

    public function toDTO(): CreatePermissionDTO
    {
        $validated = $this->validated();

        return new CreatePermissionDTO(
            code: trim((string) $validated['permissionCode']),
            name: trim((string) $validated['permissionName']),
            group: trim((string) ($validated['group'] ?? '')),
            description: trim((string) ($validated['description'] ?? '')),
            status: (string) ($validated['status'] ?? '1'),
        );
    }
}
