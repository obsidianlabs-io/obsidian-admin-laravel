<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class UpdateSessionAliasInputDTO
{
    public function __construct(
        public string $sessionId,
        public ?string $deviceAlias
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $alias = array_key_exists('deviceAlias', $input)
            ? trim((string) $input['deviceAlias'])
            : '';

        return new self(
            sessionId: (string) ($input['sessionId'] ?? ''),
            deviceAlias: $alias !== '' ? $alias : null
        );
    }
}
