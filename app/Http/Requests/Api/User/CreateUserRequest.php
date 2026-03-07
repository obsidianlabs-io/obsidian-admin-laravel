<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

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
}
