<?php

declare(strict_types=1);

namespace App\DTOs\Language;

readonly class CreateLanguageTranslationDTO
{
    public function __construct(
        public int $languageId,
        public string $translationKey,
        public string $translationValue,
        public string $description,
        public string $status,
    ) {}
}
