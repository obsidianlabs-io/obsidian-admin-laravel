<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Permission;

use App\DTOs\Permission\ListPermissionsInputDTO;
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

    public function toDTO(): ListPermissionsInputDTO
    {
        $validated = $this->validated();
        $cursor = array_key_exists('cursor', $validated) ? trim((string) $validated['cursor']) : '';

        return new ListPermissionsInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
            cursor: $cursor !== '' ? $cursor : null,
            keyword: trim((string) ($validated['keyword'] ?? '')),
            status: (string) ($validated['status'] ?? ''),
            group: trim((string) ($validated['group'] ?? '')),
        );
    }
}
