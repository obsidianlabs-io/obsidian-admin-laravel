<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\ForgotPasswordInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class ForgotPasswordRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function toDTO(): ForgotPasswordInputDTO
    {
        $validated = $this->validated();

        return new ForgotPasswordInputDTO(
            email: trim((string) $validated['email'])
        );
    }
}
