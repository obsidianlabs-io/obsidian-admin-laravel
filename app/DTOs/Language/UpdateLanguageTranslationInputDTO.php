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

    public function toUpdateLanguageTranslationDTO(int $languageId, string $fallbackStatus): UpdateLanguageTranslationDTO
    {
        return new UpdateLanguageTranslationDTO(
            languageId: $languageId,
            translationKey: $this->translationKey,
            translationValue: $this->translationValue,
            description: $this->description,
            status: $this->status ?? $fallbackStatus,
        );
    }
}
