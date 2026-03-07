<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class UpdateUserPreferencesInputDTO
{
    public function __construct(
        public bool $hasThemeSchema,
        public ?string $themeSchema,
        public bool $hasTimezone,
        public ?string $timezone
    ) {}
}
