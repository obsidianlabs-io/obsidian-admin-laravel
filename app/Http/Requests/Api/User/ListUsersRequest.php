<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use App\Http\Requests\Api\BaseApiRequest;

class ListUsersRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'current' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'userName' => ['nullable', 'string', 'max:255'],
            'userEmail' => ['nullable', 'string', 'max:255'],
            'roleCode' => ['nullable', 'string', 'max:64', 'exists:roles,code'],
            'status' => ['nullable', 'in:1,2'],
        ];
    }
}
