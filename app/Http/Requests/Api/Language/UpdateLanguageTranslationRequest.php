<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Language;

use App\Domains\System\Models\Language;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageTranslationRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $translationId = (int) $this->route('id');
        $languageId = $this->resolveLanguageId();

        return [
            'locale' => ['required', 'string', 'max:20', Rule::exists('languages', 'code')],
            'translationKey' => [
                'required',
                'string',
                'max:191',
                'regex:/^[A-Za-z0-9_.-]+$/',
                Rule::unique('language_translations', 'translation_key')
                    ->ignore($translationId)
                    ->where(static function ($query) use ($languageId): void {
                        $query->where('language_id', $languageId);
                    }),
            ],
            'translationValue' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:1,2'],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ];
    }

    private function resolveLanguageId(): int
    {
        $locale = (string) $this->input('locale', '');
        if ($locale === '') {
            return 0;
        }

        return (int) (Language::query()->where('code', $locale)->value('id') ?? 0);
    }
}
