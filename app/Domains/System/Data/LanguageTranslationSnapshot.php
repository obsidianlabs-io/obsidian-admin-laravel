<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

use App\Domains\System\Models\LanguageTranslation;

final readonly class LanguageTranslationSnapshot
{
    public function __construct(
        public string $locale,
        public string $translationKey,
        public ?string $translationValue = null,
        public ?string $status = null,
    ) {}

    public static function forCreateAudit(LanguageTranslation $translation, string $locale): self
    {
        return new self(
            locale: $locale,
            translationKey: (string) $translation->translation_key,
            status: (string) $translation->status,
        );
    }

    public static function forContentAudit(LanguageTranslation $translation, string $locale): self
    {
        return new self(
            locale: $locale,
            translationKey: (string) $translation->translation_key,
            translationValue: (string) $translation->translation_value,
            status: (string) $translation->status,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $payload = [
            'locale' => $this->locale,
            'translationKey' => $this->translationKey,
        ];

        if ($this->translationValue !== null) {
            $payload['translationValue'] = $this->translationValue;
        }

        if ($this->status !== null) {
            $payload['status'] = $this->status;
        }

        return $payload;
    }
}
