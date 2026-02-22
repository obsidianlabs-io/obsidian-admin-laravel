<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseApiRequest;

class TwoFactorCodeRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'otpCode' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }
}
