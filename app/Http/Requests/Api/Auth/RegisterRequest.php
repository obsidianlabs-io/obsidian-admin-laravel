<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\RegisterInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Support\ApiResultCode;
use App\Support\Validation\PasswordPolicy;

class RegisterRequest extends BaseApiRequest
{
    protected ApiResultCode $errorCode = ApiResultCode::LOGIN_FAILED;

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

    public function toDTO(): RegisterInputDTO
    {
        return RegisterInputDTO::fromValidated($this->validated());
    }
}
