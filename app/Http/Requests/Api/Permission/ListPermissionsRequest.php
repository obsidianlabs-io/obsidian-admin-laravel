<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Permission;

use App\Http\Requests\Api\BaseApiRequest;

class ListPermissionsRequest extends BaseApiRequest
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
            'status' => ['nullable', 'in:1,2'],
            'group' => ['nullable', 'string', 'max:50'],
        ];
    }
}
