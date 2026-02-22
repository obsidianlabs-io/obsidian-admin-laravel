<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Audit;

use App\Http\Requests\Api\BaseApiRequest;

class ListAuditPoliciesRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
