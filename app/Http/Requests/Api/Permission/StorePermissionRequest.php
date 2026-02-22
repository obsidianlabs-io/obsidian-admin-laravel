<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Permission;

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
}
