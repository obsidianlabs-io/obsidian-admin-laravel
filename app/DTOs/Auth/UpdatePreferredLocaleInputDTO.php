<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class UpdatePreferredLocaleInputDTO
{
    public function __construct(
        public string $locale
    ) {}
}
