<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\UpdateSessionAliasInputDTO;
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

    public function toDTO(): UpdateSessionAliasInputDTO
    {
        return UpdateSessionAliasInputDTO::fromValidated($this->validated());
    }
}
