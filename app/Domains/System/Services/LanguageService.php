<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Models\LanguageTranslation;

class LanguageService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    /**
     * @param  array{language_id: int, translation_key: string, translation_value: string, description?: string, status?: string}  $payload
     */
    public function createTranslation(array $payload): LanguageTranslation
    {
        $translation = LanguageTranslation::query()->create([
            'language_id' => $payload['language_id'],
            'translation_key' => $payload['translation_key'],
            'translation_value' => $payload['translation_value'],
            'description' => (string) ($payload['description'] ?? ''),
            'status' => (string) ($payload['status'] ?? '1'),
        ]);

        $this->apiCacheService->bump('languages');

        return $translation;
    }

    /**
     * @param  array{language_id?: int, translation_key?: string, translation_value?: string, description?: string, status?: string}  $payload
     */
    public function updateTranslation(LanguageTranslation $translation, array $payload): LanguageTranslation
    {
        $translation->forceFill([
            'language_id' => (int) ($payload['language_id'] ?? $translation->language_id),
            'translation_key' => (string) ($payload['translation_key'] ?? $translation->translation_key),
            'translation_value' => (string) ($payload['translation_value'] ?? $translation->translation_value),
            'description' => (string) ($payload['description'] ?? $translation->description ?? ''),
            'status' => (string) ($payload['status'] ?? $translation->status),
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
