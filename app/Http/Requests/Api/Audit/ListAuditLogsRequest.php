<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Audit;

use App\DTOs\Audit\ListAuditLogsInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class ListAuditLogsRequest extends BaseApiRequest
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
            'action' => ['nullable', 'string', 'max:120'],
            'logType' => ['nullable', 'string', 'in:login,api,operation,data,permission'],
            'userName' => ['nullable', 'string', 'max:120'],
            'requestId' => ['nullable', 'string', 'max:128'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
        ];
    }

    public function toDTO(): ListAuditLogsInputDTO
    {
        $validated = $this->validated();
        $cursor = array_key_exists('cursor', $validated) ? trim((string) $validated['cursor']) : '';

        return new ListAuditLogsInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
            cursor: $cursor !== '' ? $cursor : null,
            keyword: trim((string) ($validated['keyword'] ?? '')),
            action: trim((string) ($validated['action'] ?? '')),
            logType: trim((string) ($validated['logType'] ?? '')),
            userName: trim((string) ($validated['userName'] ?? '')),
            requestId: trim((string) ($validated['requestId'] ?? '')),
            dateFrom: trim((string) ($validated['dateFrom'] ?? '')),
            dateTo: trim((string) ($validated['dateTo'] ?? '')),
        );
    }
}
