<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use App\DTOs\User\UpdateUserInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Support\Validation\PasswordPolicy;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->route('id');

        return [
            'userName' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'roleCode' => ['required', 'string', 'exists:roles,code'],
            'organizationId' => ['nullable', 'integer', 'min:1'],
            'teamId' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:1,2'],
            'password' => ['nullable', 'string', 'max:100', PasswordPolicy::strong()],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toDTO(): UpdateUserInputDTO
    {
        $validated = $this->validated();
        $password = array_key_exists('password', $validated) && is_string($validated['password']) && $validated['password'] !== ''
            ? $validated['password']
            : null;

        return new UpdateUserInputDTO(
            userName: trim((string) $validated['userName']),
            email: trim((string) $validated['email']),
            roleCode: trim((string) $validated['roleCode']),
            organizationId: array_key_exists('organizationId', $validated) ? (int) $validated['organizationId'] : null,
            teamId: array_key_exists('teamId', $validated) ? (int) $validated['teamId'] : null,
            status: array_key_exists('status', $validated) ? (string) $validated['status'] : null,
            password: $password,
        );
    }
}
