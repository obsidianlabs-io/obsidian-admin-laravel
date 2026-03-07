<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use App\DTOs\User\CreateUserInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Support\Validation\PasswordPolicy;

class CreateUserRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'userName' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roleCode' => ['required', 'string', 'exists:roles,code'],
            'organizationId' => ['nullable', 'integer', 'min:1'],
            'teamId' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:1,2'],
            'password' => ['required', 'string', 'max:100', PasswordPolicy::strong()],
        ];
    }

    public function toDTO(): CreateUserInputDTO
    {
        $validated = $this->validated();

        return new CreateUserInputDTO(
            userName: trim((string) $validated['userName']),
            email: trim((string) $validated['email']),
            roleCode: trim((string) $validated['roleCode']),
            organizationId: array_key_exists('organizationId', $validated) ? (int) $validated['organizationId'] : null,
            teamId: array_key_exists('teamId', $validated) ? (int) $validated['teamId'] : null,
            status: (string) ($validated['status'] ?? '1'),
            password: (string) $validated['password'],
        );
    }
}
