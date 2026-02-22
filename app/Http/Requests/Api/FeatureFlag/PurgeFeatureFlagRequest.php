<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\FeatureFlag;

use App\DTOs\FeatureFlag\PurgeFeatureFlagDTO;
use App\Http\Requests\Api\BaseApiRequest;

class PurgeFeatureFlagRequest extends BaseApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
        ];
    }

    public function toDTO(): PurgeFeatureFlagDTO
    {
        $validated = $this->validated();

        return new PurgeFeatureFlagDTO(
            key: trim((string) $validated['key']),
        );
    }
}
