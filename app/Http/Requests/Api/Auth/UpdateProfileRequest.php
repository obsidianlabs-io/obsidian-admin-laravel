<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseApiRequest;

class UpdateProfileRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'userName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'currentPassword' => ['nullable', 'string', 'required_with:password'],
            'password' => ['nullable', 'string', 'max:100', 'confirmed'],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }
}
