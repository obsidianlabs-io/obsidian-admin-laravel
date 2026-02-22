<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

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
}
