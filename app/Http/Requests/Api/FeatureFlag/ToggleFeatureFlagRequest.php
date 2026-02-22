<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\FeatureFlag;

use App\DTOs\FeatureFlag\ToggleFeatureFlagDTO;
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

    public function toDTO(): ToggleFeatureFlagDTO
    {
        $validated = $this->validated();

        return new ToggleFeatureFlagDTO(
            key: trim((string) $validated['key']),
            enabled: (bool) $validated['enabled'],
        );
    }
}
