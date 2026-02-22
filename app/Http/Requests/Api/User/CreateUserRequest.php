<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use App\Http\Requests\Api\BaseApiRequest;

class CreateUserRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'userName' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roleCode' => ['required', 'string', 'exists:roles,code'],
            'status' => ['nullable', 'in:1,2'],
            'password' => ['required', 'string', 'max:100'],
        ];
    }
}
