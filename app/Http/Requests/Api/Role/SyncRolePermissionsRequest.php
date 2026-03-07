<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Role;

use App\DTOs\Role\SyncRolePermissionsInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissionCodes' => ['required', 'array'],
            'permissionCodes.*' => ['string', Rule::exists('permissions', 'code')],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toDTO(): SyncRolePermissionsInputDTO
    {
        $validated = $this->validated();
        $permissionCodes = $validated['permissionCodes'];

        return new SyncRolePermissionsInputDTO(
            permissionCodes: array_values(array_map(static fn (mixed $code): string => trim((string) $code), $permissionCodes)),
        );
    }
}
