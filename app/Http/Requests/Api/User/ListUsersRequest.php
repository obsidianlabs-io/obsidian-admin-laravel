<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\User;

use App\DTOs\User\ListUsersInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class ListUsersRequest extends BaseApiRequest
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
            'userName' => ['nullable', 'string', 'max:255'],
            'userEmail' => ['nullable', 'string', 'max:255'],
            'roleCode' => ['nullable', 'string', 'max:64', 'exists:roles,code'],
            'status' => ['nullable', 'in:1,2'],
        ];
    }

    public function toDTO(): ListUsersInputDTO
    {
        $validated = $this->validated();
        $cursor = array_key_exists('cursor', $validated) ? trim((string) $validated['cursor']) : '';

        return new ListUsersInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
            cursor: $cursor !== '' ? $cursor : null,
            keyword: trim((string) ($validated['keyword'] ?? '')),
            userName: trim((string) ($validated['userName'] ?? '')),
            userEmail: trim((string) ($validated['userEmail'] ?? '')),
            roleCode: trim((string) ($validated['roleCode'] ?? '')),
            status: (string) ($validated['status'] ?? ''),
        );
    }
}
