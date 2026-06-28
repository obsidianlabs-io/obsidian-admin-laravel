<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Data\LanguageTranslationSnapshot;
use App\Domains\System\Models\Language;
use App\Domains\System\Models\LanguageTranslation;
use App\DTOs\Language\CreateLanguageTranslationDTO;
use App\DTOs\Language\UpdateLanguageTranslationDTO;
use App\Support\LocaleDefaults;
use Illuminate\Support\Facades\DB;

class LanguageService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function createTranslation(CreateLanguageTranslationDTO $dto, ?AuditContext $audit = null): LanguageTranslation
    {
        $translation = DB::transaction(function () use ($dto): LanguageTranslation {
            return LanguageTranslation::query()->create([
                'language_id' => $dto->languageId,
                'translation_key' => $dto->translationKey,
                'translation_value' => $dto->translationValue,
                'description' => $dto->description,
                'status' => $dto->status,
            ]);
        });

        $this->apiCacheService->bump('languages');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $translation) {
                $locale = (string) (Language::query()->whereKey($translation->language_id)->value('code') ?? '');
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'language.translation.create',
                    auditable: $translation,
                    actor: $audit->actor,
                    newValues: LanguageTranslationSnapshot::forCreateAudit($translation, $locale)->toArray(),
                ));
            });
        }

        return $translation;
    }

    public function updateTranslation(LanguageTranslation $translation, UpdateLanguageTranslationDTO $dto, ?AuditContext $audit = null): LanguageTranslation
    {
        $updated = DB::transaction(function () use ($translation, $dto): LanguageTranslation {
            $translation->forceFill([
                'language_id' => $dto->languageId,
                'translation_key' => $dto->translationKey,
                'translation_value' => $dto->translationValue,
                'description' => $dto->description,
                'status' => $dto->status,
            ])->save();

            return $translation;
        });

        $this->apiCacheService->bump('languages');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                $locale = (string) (Language::query()->whereKey($updated->language_id)->value('code') ?? '');
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'language.translation.update',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: LanguageTranslationSnapshot::forContentAudit($updated, $locale)->toArray(),
                ));
            });
        }

        return $updated;
    }

    public function deleteTranslation(LanguageTranslation $translation, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($translation): void {
            $translation->delete();
        });
        $this->apiCacheService->bump('languages');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $translation) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'language.translation.delete',
                    auditable: $translation,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                ));
            });
        }
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
