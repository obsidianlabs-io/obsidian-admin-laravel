<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\FeatureFlag;

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

    public function current(): int
    {
        $validated = $this->validated();

        return max(1, (int) ($validated['current'] ?? 1));
    }

    public function size(): int
    {
        $validated = $this->validated();

        return max(1, (int) ($validated['size'] ?? 10));
    }

    public function keyword(): string
    {
        $validated = $this->validated();

        return trim((string) ($validated['keyword'] ?? ''));
    }
}
