<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant;

use App\DTOs\Tenant\ListTenantsInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class ListTenantsRequest extends BaseApiRequest
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
        ];
    }

    public function toDTO(): ListTenantsInputDTO
    {
        $validated = $this->validated();
        $cursor = array_key_exists('cursor', $validated) ? trim((string) $validated['cursor']) : '';

        return new ListTenantsInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
            cursor: $cursor !== '' ? $cursor : null,
            keyword: trim((string) ($validated['keyword'] ?? '')),
            status: (string) ($validated['status'] ?? ''),
        );
    }
}
