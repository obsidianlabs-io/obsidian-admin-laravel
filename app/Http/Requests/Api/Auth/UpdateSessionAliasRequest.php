<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseApiRequest;

class UpdateSessionAliasRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sessionId' => $this->route('sessionId'),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sessionId' => ['required', 'string', 'max:128'],
            'deviceAlias' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function sessionId(): string
    {
        return (string) $this->validated('sessionId');
    }

    public function deviceAlias(): ?string
    {
        $validated = $this->validated();
        $alias = array_key_exists('deviceAlias', $validated)
            ? trim((string) $validated['deviceAlias'])
            : '';

        return $alias !== '' ? $alias : null;
    }
}
