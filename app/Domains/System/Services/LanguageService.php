<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Models\LanguageTranslation;
use App\DTOs\Language\CreateLanguageTranslationDTO;
use App\DTOs\Language\UpdateLanguageTranslationDTO;

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
}
