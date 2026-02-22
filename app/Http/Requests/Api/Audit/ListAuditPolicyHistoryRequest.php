<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Audit;

use App\Http\Requests\Api\BaseApiRequest;

class ListAuditPolicyHistoryRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'current' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
