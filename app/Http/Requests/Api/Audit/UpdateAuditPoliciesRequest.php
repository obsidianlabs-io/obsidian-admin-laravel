<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Audit;

use App\DTOs\Audit\UpdateAuditPoliciesInputDTO;
use App\DTOs\Audit\UpdateAuditPolicyRecordInputDTO;
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

    public function toDTO(): UpdateAuditPoliciesInputDTO
    {
        $validated = $this->validated();
        $records = [];

        foreach ($validated['records'] as $record) {
            $records[] = new UpdateAuditPolicyRecordInputDTO(
                action: trim((string) ($record['action'] ?? '')),
                enabled: filter_var($record['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                samplingRate: array_key_exists('samplingRate', $record) ? $record['samplingRate'] : null,
                retentionDays: array_key_exists('retentionDays', $record) && $record['retentionDays'] !== null
                    ? (int) $record['retentionDays']
                    : null,
            );
        }

        return new UpdateAuditPoliciesInputDTO(
            records: $records,
            changeReason: trim((string) $validated['changeReason']),
        );
    }
}
