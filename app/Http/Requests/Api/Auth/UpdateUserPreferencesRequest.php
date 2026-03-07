<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\UpdateUserPreferencesInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateUserPreferencesRequest extends BaseApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'themeSchema' => [
                'nullable',
                'string',
                Rule::in(['light', 'dark', 'auto']),
                'required_without:timezone',
            ],
            'timezone' => [
                'nullable',
                'string',
                'max:64',
                'timezone',
                'required_without:themeSchema',
            ],
        ];
    }

    public function toDTO(): UpdateUserPreferencesInputDTO
    {
        $validated = $this->validated();
        $hasThemeSchema = array_key_exists('themeSchema', $validated) && $validated['themeSchema'] !== null;
        $hasTimezone = array_key_exists('timezone', $validated) && $validated['timezone'] !== null;

        return new UpdateUserPreferencesInputDTO(
            hasThemeSchema: $hasThemeSchema,
            themeSchema: $hasThemeSchema ? (string) $validated['themeSchema'] : null,
            hasTimezone: $hasTimezone,
            timezone: $hasTimezone ? (string) $validated['timezone'] : null,
        );
    }
}
