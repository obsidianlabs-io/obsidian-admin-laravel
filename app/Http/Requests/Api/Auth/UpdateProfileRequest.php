<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Domains\Access\Models\User;
use App\Http\Requests\Api\BaseApiRequest;
use App\Support\Validation\PasswordPolicy;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $authUser = $this->attributes->get('auth_user');
        $authUserId = $authUser instanceof User ? (int) $authUser->id : null;
        $userNameUnique = Rule::unique('users', 'name');
        $emailUnique = Rule::unique('users', 'email');
        if ($authUserId !== null && $authUserId > 0) {
            $userNameUnique = $userNameUnique->ignore($authUserId);
            $emailUnique = $emailUnique->ignore($authUserId);
        }

        return [
            'userName' => ['required', 'string', 'max:255', $userNameUnique],
            'email' => ['required', 'email', 'max:255', $emailUnique],
            'currentPassword' => ['nullable', 'string', 'required_with:password'],
            'password' => ['nullable', 'string', 'max:100', PasswordPolicy::strong(), 'confirmed'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }
}
