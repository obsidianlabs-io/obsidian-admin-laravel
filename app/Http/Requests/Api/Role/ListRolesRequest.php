<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Role;

use App\DTOs\Role\ListRolesInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class ListRolesRequest extends BaseApiRequest
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
            'level' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function toDTO(): ListRolesInputDTO
    {
        $validated = $this->validated();
        $cursor = array_key_exists('cursor', $validated) ? trim((string) $validated['cursor']) : '';

        return new ListRolesInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
            cursor: $cursor !== '' ? $cursor : null,
            keyword: trim((string) ($validated['keyword'] ?? '')),
            status: (string) ($validated['status'] ?? ''),
            level: array_key_exists('level', $validated) ? (int) $validated['level'] : null,
        );
    }
}
