<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\RefreshTokenInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Support\ApiResultCode;

class RefreshTokenRequest extends BaseApiRequest
{
    protected ApiResultCode $errorCode = ApiResultCode::UNAUTHORIZED;

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
