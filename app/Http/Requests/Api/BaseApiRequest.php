<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseApiRequest extends FormRequest
{
    protected string $errorCode = '1002';

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        /** @var array<string, list<string>> $errors */
        $errors = collect($validator->errors()->toArray())
            ->map(static function (array $messages): array {
                return array_values(array_map(
                    static fn (mixed $message): string => (string) $message,
                    $messages
                ));
            })
            ->all();

        throw new HttpResponseException(ApiErrorResponse::json(
            $this,
            $this->errorCode,
            (string) $validator->errors()->first(),
            ['errors' => $errors]
        ));
    }
}
