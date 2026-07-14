<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\FeatureFlag;

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

    public function key(): string
    {
        $validated = $this->validated();

        return trim((string) $validated['key']);
    }
}
