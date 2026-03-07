<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\UpdatePreferredLocaleInputDTO;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdatePreferredLocaleRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        $locale = (string) $this->input('locale', '');
        if ($locale !== '') {
            return;
        }

        $legacyPreferredLocale = (string) $this->input('preferredLocale', '');
        if ($legacyPreferredLocale !== '') {
            $this->merge([
                'locale' => $legacyPreferredLocale,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                Rule::exists('languages', 'code')->where(static function ($query): void {
                    $query->where('status', '1');
                }),
            ],
        ];
    }

    public function toDTO(): UpdatePreferredLocaleInputDTO
    {
        $validated = $this->validated();

        return new UpdatePreferredLocaleInputDTO(
            locale: trim((string) $validated['locale'])
        );
    }
}
