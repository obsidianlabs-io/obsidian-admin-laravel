<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\DTOs\Auth\RevokeSessionInputDTO;
use App\Http\Requests\Api\BaseApiRequest;

class RevokeSessionRequest extends BaseApiRequest
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
        ];
    }

    public function toDTO(): RevokeSessionInputDTO
    {
        return RevokeSessionInputDTO::fromValidated($this->validated());
    }
}
