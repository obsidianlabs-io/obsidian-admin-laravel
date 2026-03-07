<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Actions\ListLanguageTranslationsQueryAction;
use App\Domains\System\Data\LanguageTranslationSnapshot;
use App\Domains\System\Http\Resources\LanguageTranslationListResource;
use App\Domains\System\Models\Language;
use App\Domains\System\Models\LanguageTranslation;
use App\Domains\System\Services\AuditLogService;
use App\Domains\System\Services\LanguageService;
use App\Http\Requests\Api\Language\ListLanguageTranslationsRequest;
use App\Http\Requests\Api\Language\StoreLanguageTranslationRequest;
use App\Http\Requests\Api\Language\UpdateLanguageTranslationRequest;
use App\Support\LocaleDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends ApiController
{
    public function __construct(
        private readonly LanguageService $languageService,
        private readonly AuditLogService $auditLogService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function list(
        ListLanguageTranslationsRequest $request,
        ListLanguageTranslationsQueryAction $listLanguageTranslationsQuery,
    ): JsonResponse {
        $authResult = $this->authorizeLanguageConsole($request, 'language.view');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $input = $request->toDTO();
        $query = $listLanguageTranslationsQuery->handle($input);

        if ($input->usesCursorPagination((string) $request->input('paginationMode', ''))) {
            $page = $this->cursorPaginateById(
                clone $query,
                $input->size,
                $input->cursor,
                false
            );
            $records = LanguageTranslationListResource::collection($page['records'])->resolve($request);

            return $this->success($this->cursorPaginationPayload($page, $records)->toArray());
        }

        $total = (clone $query)->count();
        $records = LanguageTranslationListResource::collection(
            $query->orderBy('language_id')
                ->orderBy('translation_key')
                ->forPage($input->current, $input->size)
                ->get()
        )->resolve($request);

        return $this->success(
            $this->offsetPaginationPayload($input->current, $input->size, $total, $records)->toArray()
        );
    }

    public function options(Request $request): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.view');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $records = $this->apiCacheService->remember(
            'languages',
            'options',
            static function (): array {
                return Language::query()
                    ->orderByDesc('is_default')
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get(['id', 'code', 'name', 'status', 'is_default'])
                    ->map(static function (Language $language): array {
                        return [
                            'id' => $language->id,
                            'locale' => (string) $language->code,
                            'localeName' => (string) $language->name,
                            'status' => (string) $language->status,
                            'isDefault' => (bool) $language->is_default,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        return $this->success([
            'records' => $records,
        ]);
    }

    public function locales(): JsonResponse
    {
        $records = $this->resolveRuntimeLocales();

        return $this->success([
            'records' => $records,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        return $this->success([
            'defaultLocale' => $this->resolveDefaultLocaleCode(),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $requestedLocale = trim((string) $request->query('locale', ''));

        $language = $this->resolveRuntimeLanguage($requestedLocale);
        if (! $language) {
            return $this->success([
                'locale' => $requestedLocale !== '' ? $requestedLocale : $this->resolveDefaultLocaleCode(),
                'version' => '0',
                'notModified' => true,
                'messages' => [],
            ]);
        }

        $bundle = $this->apiCacheService->remember(
            'languages',
            'messages|locale:'.$language->code,
            function () use ($language): array {
                /** @var object{total_count?: int|string, max_updated_at?: string|null}|null $meta */
                $meta = LanguageTranslation::query()
                    ->where('language_id', $language->id)
                    ->selectRaw('COUNT(*) as total_count, MAX(updated_at) as max_updated_at')
                    ->toBase()
                    ->first();

                $maxUpdatedAt = '';
                if ($meta !== null && isset($meta->max_updated_at)) {
                    $maxUpdatedAt = $meta->max_updated_at;
                }
                $count = $meta !== null && isset($meta->total_count) ? (int) $meta->total_count : 0;
                $updatedTimestamp = $language->updated_at !== null ? (int) $language->updated_at->timestamp : 0;

                $version = sha1(implode('|', [
                    (string) $language->id,
                    $updatedTimestamp,
                    $count,
                    $maxUpdatedAt,
                ]));

                $messages = LanguageTranslation::query()
                    ->where('language_id', $language->id)
                    ->where('status', '1')
                    ->orderBy('translation_key')
                    ->pluck('translation_value', 'translation_key')
                    ->mapWithKeys(static function ($value, $key): array {
                        return [(string) $key => (string) $value];
                    })
                    ->all();

                return [
                    'version' => $version,
                    'messages' => $messages,
                ];
            }
        );
        $version = (string) $bundle['version'];
        $messages = $bundle['messages'];

        $clientVersion = trim((string) $request->query('version', ''));
        if ($clientVersion !== '' && hash_equals($version, $clientVersion)) {
            return $this->success([
                'locale' => (string) $language->code,
                'version' => $version,
                'notModified' => true,
                'messages' => [],
            ]);
        }

        return $this->success([
            'locale' => (string) $language->code,
            'version' => $version,
            'notModified' => false,
            'messages' => $messages,
        ]);
    }

    public function store(StoreLanguageTranslationRequest $request): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.manage');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $input = $request->toDTO();
        $language = Language::query()->where('code', $input->locale)->first();
        if (! $language) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language locale not found');
        }

        return $this->withIdempotency($request, $user, function () use ($language, $input, $user, $request): JsonResponse {
            $translation = $this->languageService->createTranslation($input->toCreateLanguageTranslationDTO((int) $language->id));

            $this->auditLogService->record(
                action: 'language.translation.create',
                auditable: $translation,
                actor: $user,
                request: $request,
                newValues: LanguageTranslationSnapshot::forCreateAudit($translation, (string) $language->code)->toArray()
            );

            return $this->success([
                'id' => $translation->id,
            ], 'Language translation created');
        });
    }

    public function update(UpdateLanguageTranslationRequest $request, int $id): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.manage');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $translation = LanguageTranslation::query()->find($id);
        if (! $translation) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language translation not found');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $translation, 'Language translation');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $input = $request->toDTO();
        $language = Language::query()->where('code', $input->locale)->first();
        if (! $language) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language locale not found');
        }

        $oldValues = LanguageTranslationSnapshot::forContentAudit(
            $translation,
            $this->resolveTranslationLocale($translation)
        )->toArray();

        $translation = $this->languageService->updateTranslation(
            $translation,
            $input->toUpdateLanguageTranslationDTO((int) $language->id, (string) $translation->status)
        );

        $this->auditLogService->record(
            action: 'language.translation.update',
            auditable: $translation,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: LanguageTranslationSnapshot::forContentAudit($translation, (string) $language->code)->toArray()
        );

        return $this->success([
            'id' => $translation->id,
        ], 'Language translation updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.manage');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $translation = LanguageTranslation::query()->with('language:id,code')->find($id);
        if (! $translation) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language translation not found');
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $oldValues = LanguageTranslationSnapshot::forContentAudit(
            $translation,
            $this->resolveTranslationLocale($translation)
        )->toArray();

        $this->languageService->deleteTranslation($translation);
        $this->auditLogService->record(
            action: 'language.translation.delete',
            auditable: $translation,
            actor: $user,
            request: $request,
            oldValues: $oldValues
        );

        return $this->success([], 'Language translation deleted');
    }

    private function authorizeLanguageConsole(Request $request, string $permissionCode): ApiAuthResult
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if (! $this->isSuperAdmin($user)) {
            return ApiAuthResult::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $selectedTenantHeader = $request->header('X-Tenant-Id');
        $selectedTenantId = is_numeric($selectedTenantHeader) ? (int) $selectedTenantHeader : 0;
        if ($selectedTenantId > 0) {
            return ApiAuthResult::failure(self::FORBIDDEN_CODE, 'Switch to No Tenant to manage languages');
        }

        return ApiAuthResult::success($user, $authResult->token());
    }

    private function resolveRuntimeLanguage(string $requestedLocale): ?Language
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
    private function resolveRuntimeLocales(): array
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

    private function resolveDefaultLocaleCode(): string
    {
        return LocaleDefaults::resolve();
    }

    private function resolveTranslationLocale(LanguageTranslation $translation): string
    {
        $language = $translation->language;

        return $language instanceof Language ? (string) $language->code : '';
    }
}
