<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
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

    public function list(ListLanguageTranslationsRequest $request): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.view');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $validated = $request->validated();
        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $locale = trim((string) ($validated['locale'] ?? ''));
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');

        $query = LanguageTranslation::query()
            ->with('language:id,code,name,status')
            ->select(['id', 'language_id', 'translation_key', 'translation_value', 'description', 'status', 'created_at', 'updated_at']);

        if ($locale !== '') {
            $query->whereHas('language', static function ($builder) use ($locale): void {
                $builder->where('code', $locale);
            });
        }

        if ($keyword !== '') {
            $query->where(static function ($builder) use ($keyword): void {
                $builder->where('translation_key', 'like', '%'.$keyword.'%')
                    ->orWhere('translation_value', 'like', '%'.$keyword.'%');
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($this->hasCursorPagination($validated)) {
            $page = $this->cursorPaginateById(
                clone $query,
                $size,
                (string) ($validated['cursor'] ?? ''),
                false
            );
            $records = LanguageTranslationListResource::collection($page['records'])->resolve($request);

            return $this->success([
                'paginationMode' => 'cursor',
                'size' => $page['size'],
                'hasMore' => $page['hasMore'],
                'nextCursor' => $page['nextCursor'],
                'records' => $records,
            ]);
        }

        $total = (clone $query)->count();
        $records = LanguageTranslationListResource::collection(
            $query->orderBy('language_id')
                ->orderBy('translation_key')
                ->forPage($current, $size)
                ->get()
        )->resolve($request);

        return $this->success([
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'records' => $records,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.view');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
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
                $meta = LanguageTranslation::query()
                    ->where('language_id', $language->id)
                    ->selectRaw('COUNT(*) as total_count, MAX(updated_at) as max_updated_at')
                    ->first();

                $maxUpdatedAt = $meta?->max_updated_at ? (string) $meta->max_updated_at : '';
                $count = (int) ($meta?->total_count ?? 0);

                $version = sha1(implode('|', [
                    (string) $language->id,
                    $language->updated_at?->timestamp ?? 0,
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
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $validated = $request->validated();
        $language = Language::query()->where('code', (string) $validated['locale'])->first();
        if (! $language) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language locale not found');
        }

        return $this->withIdempotency($request, $user, function () use ($language, $validated, $user, $request): JsonResponse {
            $translation = $this->languageService->createTranslation([
                'language_id' => (int) $language->id,
                'translation_key' => (string) $validated['translationKey'],
                'translation_value' => (string) $validated['translationValue'],
                'description' => (string) ($validated['description'] ?? ''),
                'status' => (string) ($validated['status'] ?? '1'),
            ]);

            $this->auditLogService->record(
                action: 'language.translation.create',
                auditable: $translation,
                actor: $user,
                request: $request,
                newValues: [
                    'locale' => $language->code,
                    'translationKey' => $translation->translation_key,
                    'status' => (string) $translation->status,
                ]
            );

            return $this->success([
                'id' => $translation->id,
            ], 'Language translation created');
        });
    }

    public function update(UpdateLanguageTranslationRequest $request, int $id): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.manage');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $translation = LanguageTranslation::query()->find($id);
        if (! $translation) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language translation not found');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $translation, 'Language translation');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $validated = $request->validated();

        $language = Language::query()->where('code', (string) $validated['locale'])->first();
        if (! $language) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language locale not found');
        }

        $oldValues = [
            'locale' => $translation->language?->code,
            'translationKey' => $translation->translation_key,
            'translationValue' => $translation->translation_value,
            'status' => (string) $translation->status,
        ];

        $translation = $this->languageService->updateTranslation($translation, [
            'language_id' => (int) $language->id,
            'translation_key' => (string) $validated['translationKey'],
            'translation_value' => (string) $validated['translationValue'],
            'description' => (string) ($validated['description'] ?? ''),
            'status' => (string) ($validated['status'] ?? $translation->status),
        ]);

        $this->auditLogService->record(
            action: 'language.translation.update',
            auditable: $translation,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: [
                'locale' => $language->code,
                'translationKey' => $translation->translation_key,
                'translationValue' => $translation->translation_value,
                'status' => (string) $translation->status,
            ]
        );

        return $this->success([
            'id' => $translation->id,
        ], 'Language translation updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->authorizeLanguageConsole($request, 'language.manage');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $translation = LanguageTranslation::query()->with('language:id,code')->find($id);
        if (! $translation) {
            return $this->error(self::PARAM_ERROR_CODE, 'Language translation not found');
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $oldValues = [
            'locale' => (string) ($translation->language?->code ?? ''),
            'translationKey' => (string) $translation->translation_key,
            'translationValue' => (string) $translation->translation_value,
            'status' => (string) $translation->status,
        ];

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

    /**
     * @return array{ok: bool, code: string, msg: string, user?: \App\Domains\Access\Models\User, token?: \Laravel\Sanctum\PersonalAccessToken}
     */
    private function authorizeLanguageConsole(Request $request, string $permissionCode): array
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        if (! $authResult['ok']) {
            return $authResult;
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        if (! $this->isSuperAdmin($user)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $selectedTenantId = (int) $request->header('X-Tenant-Id', 0);
        if ($selectedTenantId > 0) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Switch to No Tenant to manage languages',
            ];
        }

        return $authResult;
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
        return $this->apiCacheService->remember(
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
    }

    private function resolveDefaultLocaleCode(): string
    {
        return LocaleDefaults::resolve();
    }
}
