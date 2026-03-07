<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseApiRequest;
use App\Support\Validation\PasswordPolicy;

class RegisterRequest extends BaseApiRequest
{
    protected string $errorCode = '1001';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:100', PasswordPolicy::strong()],
        ];
    }
}
