<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\RefreshTokenInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class RefreshTokenRequest extends BaseApiRequest
{
    protected string $errorCode = '8888';

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'refreshToken' => ['required', 'string'],
        ];
    }

    public function toDTO(): RefreshTokenInputDTO
    {
        return RefreshTokenInputDTO::fromValidated($this->validated());
    }
}
