<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Team;

use App\DTOs\Team\ListTeamsInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class ListTeamsRequest extends BaseApiRequest
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
            'organizationId' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): ListTeamsInputDTO
    {
        $validated = $this->validated();
        $cursor = array_key_exists('cursor', $validated) ? trim((string) $validated['cursor']) : '';

        return new ListTeamsInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
            cursor: $cursor !== '' ? $cursor : null,
            keyword: trim((string) ($validated['keyword'] ?? '')),
            status: (string) ($validated['status'] ?? ''),
            organizationId: array_key_exists('organizationId', $validated) ? (int) $validated['organizationId'] : null,
        );
    }
}
