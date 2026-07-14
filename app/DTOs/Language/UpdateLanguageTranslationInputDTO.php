<?php

declare(strict_types=1);

namespace App\DTOs\Language;

final readonly class UpdateLanguageTranslationInputDTO
{
    public function __construct(
        public string $locale,
        public string $translationKey,
        public string $translationValue,
        public string $description,
        public ?string $status
    ) {}
}
