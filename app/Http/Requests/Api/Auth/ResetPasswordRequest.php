<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\ResetPasswordInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Support\Validation\PasswordPolicy;

class ResetPasswordRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:100', PasswordPolicy::strong(), 'confirmed'],
            'password_confirmation' => ['required', 'string', 'max:100'],
        ];
    }

    public function toDTO(): ResetPasswordInputDTO
    {
        $validated = $this->validated();

        return new ResetPasswordInputDTO(
            token: (string) $validated['token'],
            email: trim((string) $validated['email']),
            password: (string) $validated['password'],
            passwordConfirmation: (string) $validated['password_confirmation'],
        );
    }
}
