<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Permission;

use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permissionId = (int) $this->route('id');

        return [
            'permissionCode' => ['required', 'string', 'max:100', Rule::unique('permissions', 'code')->ignore($permissionId)],
            'permissionName' => ['required', 'string', 'max:100', Rule::unique('permissions', 'name')->ignore($permissionId)],
            'group' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:1,2'],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }
}
