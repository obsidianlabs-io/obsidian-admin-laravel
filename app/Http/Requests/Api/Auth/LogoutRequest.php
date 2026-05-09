<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\LogoutInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Support\ApiResultCode;

class LogoutRequest extends BaseApiRequest
{
    protected ApiResultCode $errorCode = ApiResultCode::UNAUTHORIZED;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'refreshToken' => ['sometimes', 'string'],
        ];
    }

    public function toDTO(): LogoutInputDTO
    {
        return LogoutInputDTO::fromValidated($this->validated());
    }
}
