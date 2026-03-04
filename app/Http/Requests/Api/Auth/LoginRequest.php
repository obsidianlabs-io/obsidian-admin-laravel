<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseApiRequest;
use App\Support\AppLocale;
use Illuminate\Validation\Rule;

class LoginRequest extends BaseApiRequest
{
    protected string $errorCode = '1001';

    protected function prepareForValidation(): void
    {
        $locale = trim((string) $this->input('locale', ''));
        if ($locale === '') {
            return;
        }

        $normalized = AppLocale::toPreferredLocaleCode($locale);

        $this->merge([
            'locale' => $normalized ?? $locale,
        ]);
    }

    /**
     * @return array<string, list<\Illuminate\Validation\Rules\Exists|string>>
     */
    public function rules(): array
    {
        return [
            'userName' => ['nullable', 'string', 'required_without:email'],
            'email' => ['nullable', 'email', 'required_without:userName'],
            'password' => ['required', 'string'],
            'rememberMe' => ['sometimes', 'boolean'],
            'otpCode' => ['nullable', 'string', 'max:10'],
            'locale' => [
                'sometimes',
                'string',
                Rule::exists('languages', 'code')->where(static function ($query): void {
                    $query->where('status', '1');
                }),
            ],
        ];
    }
}
