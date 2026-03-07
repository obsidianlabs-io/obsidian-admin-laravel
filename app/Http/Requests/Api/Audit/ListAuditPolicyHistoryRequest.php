<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Audit;

use App\DTOs\Audit\ListAuditPolicyHistoryInputDTO;
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

    public function toDTO(): ListAuditPolicyHistoryInputDTO
    {
        $validated = $this->validated();

        return new ListAuditPolicyHistoryInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
        );
    }
}
