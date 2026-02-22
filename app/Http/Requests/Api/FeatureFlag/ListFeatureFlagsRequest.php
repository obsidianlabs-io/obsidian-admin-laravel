<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\FeatureFlag;

use App\DTOs\FeatureFlag\ListFeatureFlagsDTO;
use App\Http\Requests\Api\BaseApiRequest;

class ListFeatureFlagsRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'current' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDTO(): ListFeatureFlagsDTO
    {
        $validated = $this->validated();

        return new ListFeatureFlagsDTO(
            current: max(1, (int) ($validated['current'] ?? 1)),
            size: max(1, (int) ($validated['size'] ?? 10)),
            keyword: trim((string) ($validated['keyword'] ?? '')),
        );
    }
}
