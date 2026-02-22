<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Language;

use App\Http\Requests\Api\BaseApiRequest;

class ListLanguageTranslationsRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:20'],
            'keyword' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'in:1,2'],
        ];
    }
}
