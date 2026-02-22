<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Audit;

use App\Http\Requests\Api\BaseApiRequest;

class UpdateAuditPoliciesRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*.action' => ['required', 'string', 'max:120'],
            'records.*.enabled' => ['required', 'boolean'],
            'records.*.samplingRate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'records.*.retentionDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'changeReason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
