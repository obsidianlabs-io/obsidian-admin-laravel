<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Language;

use App\DTOs\Language\ListLanguageTranslationsInputDTO;
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

    public function toDTO(): ListLanguageTranslationsInputDTO
    {
        $validated = $this->validated();
        $cursor = array_key_exists('cursor', $validated) ? trim((string) $validated['cursor']) : '';

        return new ListLanguageTranslationsInputDTO(
            current: (int) ($validated['current'] ?? 1),
            size: (int) ($validated['size'] ?? 10),
            cursor: $cursor !== '' ? $cursor : null,
            locale: trim((string) ($validated['locale'] ?? '')),
            keyword: trim((string) ($validated['keyword'] ?? '')),
            status: (string) ($validated['status'] ?? ''),
        );
    }
}
