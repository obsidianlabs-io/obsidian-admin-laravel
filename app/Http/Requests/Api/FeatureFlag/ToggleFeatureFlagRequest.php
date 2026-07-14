<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\FeatureFlag;

use App\Http\Requests\Api\BaseApiRequest;

class ToggleFeatureFlagRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function key(): string
    {
        $validated = $this->validated();

        return trim((string) $validated['key']);
    }

    public function enabled(): bool
    {
        $validated = $this->validated();

        return (bool) $validated['enabled'];
    }
}
