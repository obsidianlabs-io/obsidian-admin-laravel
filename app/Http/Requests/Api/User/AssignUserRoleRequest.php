<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use App\DTOs\User\AssignUserRoleInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class AssignUserRoleRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'roleCode' => ['required', 'string', 'exists:roles,code'],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toDTO(): AssignUserRoleInputDTO
    {
        $validated = $this->validated();

        return new AssignUserRoleInputDTO(
            roleCode: trim((string) $validated['roleCode'])
        );
    }
}
