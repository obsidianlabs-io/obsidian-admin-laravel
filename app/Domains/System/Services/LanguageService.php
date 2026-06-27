<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Models\Language;
use App\Domains\System\Models\LanguageTranslation;
use App\DTOs\Language\CreateLanguageTranslationDTO;
use App\DTOs\Language\UpdateLanguageTranslationDTO;
use App\Support\LocaleDefaults;

class LanguageService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function createTranslation(CreateLanguageTranslationDTO $dto): LanguageTranslation
    {
        $translation = LanguageTranslation::query()->create([
            'language_id' => $dto->languageId,
            'translation_key' => $dto->translationKey,
            'translation_value' => $dto->translationValue,
            'description' => $dto->description,
            'status' => $dto->status,
        ]);

        $this->apiCacheService->bump('languages');

        return $translation;
    }

    public function updateTranslation(LanguageTranslation $translation, UpdateLanguageTranslationDTO $dto): LanguageTranslation
    {
        $translation->forceFill([
            'language_id' => $dto->languageId,
            'translation_key' => $dto->translationKey,
            'translation_value' => $dto->translationValue,
            'description' => $dto->description,
            'status' => $dto->status,
        ])->save();
        $this->apiCacheService->bump('languages');

        return $translation;
    }

    public function deleteTranslation(LanguageTranslation $translation): void
    {
        $translation->delete();
        $this->apiCacheService->bump('languages');
    }

    /**
     * Resolve the runtime language for the given locale code, falling back
     * to the configured default, then to the first active language.
     */
    public function resolveRuntimeLanguage(string $requestedLocale): ?Language
    {
        if ($requestedLocale !== '') {
            $direct = Language::query()
                ->where('code', $requestedLocale)
                ->where('status', '1')
                ->first();

            if ($direct) {
                return $direct;
            }
        }

        $configuredDefault = LocaleDefaults::configured();
        $configured = Language::query()
            ->where('code', $configuredDefault)
            ->where('status', '1')
            ->first();

        if ($configured) {
            return $configured;
        }

        return Language::query()
            ->where('status', '1')
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<array{id: int, locale: string, localeName: string, isDefault: bool}>
     */
    public function resolveRuntimeLocales(): array
    {
        /** @var list<array{id: int, locale: string, localeName: string, isDefault: bool}> $locales */
        $locales = $this->apiCacheService->remember(
            'languages',
            'locales',
            static function (): array {
                return Language::query()
                    ->where('status', '1')
                    ->orderByDesc('is_default')
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get(['id', 'code', 'name', 'is_default'])
                    ->map(static function (Language $language): array {
                        return [
                            'id' => $language->id,
                            'locale' => (string) $language->code,
                            'localeName' => (string) $language->name,
                            'isDefault' => (bool) $language->is_default,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        return $locales;
    }

    public function resolveDefaultLocaleCode(): string
    {
        return LocaleDefaults::resolve();
    }

    public function resolveTranslationLocale(LanguageTranslation $translation): string
    {
        $language = $translation->language;

        return $language instanceof Language ? (string) $language->code : '';
    }
}
