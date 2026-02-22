<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseApiRequest;
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

        $normalized = strtolower(str_replace('_', '-', $locale));
        $alias = [
            'en' => 'en-US',
            'en-us' => 'en-US',
            'zh' => 'zh-CN',
            'cn' => 'zh-CN',
            'zh-cn' => 'zh-CN',
        ];

        $this->merge([
            'locale' => $alias[$normalized] ?? $locale,
        ]);
    }

    /**
     * @return array<string, list<string>>
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
