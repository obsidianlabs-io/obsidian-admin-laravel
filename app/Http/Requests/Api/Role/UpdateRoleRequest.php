<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Role;

use App\DTOs\Role\UpdateRoleInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseApiRequest
{
    private const ROLE_LEVEL_MIN = 1;

    private const ROLE_LEVEL_MAX = 999;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roleCode' => ['required', 'string', 'max:64'],
            'roleName' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:1,2'],
            'level' => ['required', 'integer', 'min:'.self::ROLE_LEVEL_MIN, 'max:'.self::ROLE_LEVEL_MAX],
            'permissionCodes' => ['nullable', 'array'],
            'permissionCodes.*' => ['string', Rule::exists('permissions', 'code')],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toDTO(): UpdateRoleInputDTO
    {
        $validated = $this->validated();
        $hasPermissionCodes = array_key_exists('permissionCodes', $validated);
        $permissionCodes = $validated['permissionCodes'] ?? [];
        if (! is_array($permissionCodes)) {
            $permissionCodes = [];
        }

        return new UpdateRoleInputDTO(
            roleCode: trim((string) $validated['roleCode']),
            roleName: trim((string) $validated['roleName']),
            description: trim((string) ($validated['description'] ?? '')),
            status: array_key_exists('status', $validated) ? (string) $validated['status'] : null,
            level: (int) $validated['level'],
            hasPermissionCodes: $hasPermissionCodes,
            permissionCodes: array_values(array_map(static fn (mixed $code): string => trim((string) $code), $permissionCodes)),
        );
    }
}
