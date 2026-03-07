<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Role;

use App\DTOs\Role\StoreRoleInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends BaseApiRequest
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
        ];
    }

    public function toDTO(): StoreRoleInputDTO
    {
        $validated = $this->validated();
        $permissionCodes = $validated['permissionCodes'] ?? [];
        if (! is_array($permissionCodes)) {
            $permissionCodes = [];
        }

        return new StoreRoleInputDTO(
            roleCode: trim((string) $validated['roleCode']),
            roleName: trim((string) $validated['roleName']),
            description: trim((string) ($validated['description'] ?? '')),
            status: (string) ($validated['status'] ?? '1'),
            level: (int) $validated['level'],
            permissionCodes: array_values(array_map(static fn (mixed $code): string => trim((string) $code), $permissionCodes)),
        );
    }
}
