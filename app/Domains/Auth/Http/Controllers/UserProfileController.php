<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Models\UserPreference;
use App\Http\Requests\Api\Auth\UpdatePreferredLocaleRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Auth\UpdateUserPreferencesRequest;
use App\Support\ApiDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserProfileController extends AbstractUserController
{
    public function getUserInfo(Request $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $user->loadMissing('role.permissions', 'tenant', 'preference');
        $tenantContext = $this->resolveTenantContext($request, $user);
        if (! $tenantContext['ok']) {
            return $this->error($tenantContext['code'], $tenantContext['msg']);
        }
        $roles = $this->resolveRoles($user);
        $permissionCodes = $user->permissionCodes();
        $locale = $this->resolveLocale($user);
        $timezone = $this->resolveTimezone($user);
        $navigation = $this->menuMetadataService->resolveForUser(
            user: $user,
            tenantId: $tenantContext['tenantId'],
            roleCodes: $roles,
            permissionCodes: $permissionCodes
        );
        $themeSchema = $this->resolveThemeSchema($user);
        $themeConfig = $this->themeConfigService->resolveEffectiveConfig(
            null,
            $themeSchema
        );

        return $this->success([
            'userId' => (string) $user->id,
            'userName' => $user->name,
            'locale' => $locale,
            'preferredLocale' => $locale,
            'timezone' => $timezone,
            'themeSchema' => $themeSchema,
            'themeConfig' => $themeConfig['config'],
            'themeProfileVersion' => (int) $themeConfig['profileVersion'],
            'roles' => $roles,
            'buttons' => $permissionCodes,
            'currentTenantId' => (string) $tenantContext['tenantId'],
            'currentTenantName' => $tenantContext['tenantName'],
            'tenants' => $tenantContext['tenants'],
            'menuScope' => $navigation['menuScope'],
            'menus' => $navigation['menus'],
            'routeRules' => $navigation['routeRules'],
        ]);
    }

    public function menus(Request $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $user->loadMissing('role.permissions', 'tenant');

        $tenantContext = $this->resolveTenantContext($request, $user);
        if (! $tenantContext['ok']) {
            return $this->error($tenantContext['code'], $tenantContext['msg']);
        }

        $navigation = $this->menuMetadataService->resolveForUser(
            user: $user,
            tenantId: $tenantContext['tenantId'],
            roleCodes: $this->resolveRoles($user),
            permissionCodes: $user->permissionCodes()
        );

        return $this->success([
            'menuScope' => $navigation['menuScope'],
            'menus' => $navigation['menus'],
            'routeRules' => $navigation['routeRules'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->getUserInfo($request);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];

        return $this->success($this->resolveProfile($user));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];

        $optimisticLockError = $this->ensureOptimisticLock($request, $user, 'User profile');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $validated = $request->validated();
        $uniqueValidator = Validator::make($validated, [
            'userName' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        if ($uniqueValidator->fails()) {
            return $this->error(self::PARAM_ERROR_CODE, $uniqueValidator->errors()->first());
        }

        $passwordValidator = Validator::make($validated, [
            'password' => ['nullable', 'string', 'max:100', $this->strongPasswordRule()],
        ]);
        if ($passwordValidator->fails()) {
            return $this->error(self::PARAM_ERROR_CODE, $passwordValidator->errors()->first());
        }

        $oldValues = [
            'userName' => $user->name,
            'email' => $user->email,
        ];

        $payload = [
            'name' => trim((string) $validated['userName']),
            'email' => trim((string) $validated['email']),
        ];

        $password = (string) ($validated['password'] ?? '');
        if ($password !== '') {
            $currentPassword = (string) ($validated['currentPassword'] ?? '');
            if (! Hash::check($currentPassword, $user->password)) {
                return $this->error(self::PARAM_ERROR_CODE, 'Current password is incorrect');
            }

            $payload['password'] = $password;
        }

        $this->userService->update($user, new \App\DTOs\User\UpdateUserDTO(
            name: $payload['name'],
            email: $payload['email'],
            password: $payload['password'] ?? null,
            status: (string) $user->status,
            roleId: (int) $user->role_id,
            tenantId: $user->tenant_id ? (int) $user->tenant_id : null,
        ));
        $this->auditLogService->record(
            action: 'user.profile.update',
            auditable: $user,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: [
                'userName' => $user->name,
                'email' => $user->email,
            ],
            tenantId: $user->tenant_id ? (int) $user->tenant_id : null
        );

        return $this->success($this->resolveProfile($user), 'Profile updated');
    }

    public function updatePreferredLocale(UpdatePreferredLocaleRequest $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];

        $locale = (string) $request->validated()['locale'];
        $oldLocale = $this->resolveLocale($user);
        if ($oldLocale === $locale) {
            return $this->success([
                'locale' => $locale,
                'preferredLocale' => $locale,
            ], 'Preferred locale updated');
        }

        UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['locale' => $locale]
        );
        $this->resolveUserContext->invalidateUserContextCache();

        $this->auditLogService->record(
            action: 'user.locale.update',
            auditable: $user,
            actor: $user,
            request: $request,
            oldValues: ['locale' => $oldLocale],
            newValues: ['locale' => $locale],
            tenantId: $user->tenant_id ? (int) $user->tenant_id : null
        );

        return $this->success([
            'locale' => $locale,
            'preferredLocale' => $locale,
        ], 'Preferred locale updated');
    }

    public function updateUserPreferences(UpdateUserPreferencesRequest $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $validated = $request->validated();
        $oldThemeSchema = $this->resolveThemeSchema($user);
        $oldTimezone = $this->resolveTimezone($user);
        $hasThemeSchema = array_key_exists('themeSchema', $validated) && $validated['themeSchema'] !== null;
        $hasTimezone = array_key_exists('timezone', $validated) && $validated['timezone'] !== null;
        $themeSchema = $hasThemeSchema ? (string) $validated['themeSchema'] : $oldThemeSchema;
        $timezone = $hasTimezone
            ? ApiDateTime::normalizeTimezone((string) $validated['timezone'])
            : $oldTimezone;

        if (($oldThemeSchema ?? '') === ($themeSchema ?? '') && $oldTimezone === $timezone) {
            return $this->success([
                'themeSchema' => $themeSchema,
                'timezone' => $timezone,
            ], 'Preferences updated');
        }

        $payload = [];
        if ($hasThemeSchema) {
            $payload['theme_schema'] = $themeSchema;
        }
        if ($hasTimezone) {
            $payload['timezone'] = $timezone;
        }

        UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            $payload
        );
        $this->resolveUserContext->invalidateUserContextCache();

        ApiDateTime::assignRequestTimezone($request, $timezone);

        $this->auditLogService->record(
            action: 'user.preferences.update',
            auditable: $user,
            actor: $user,
            request: $request,
            oldValues: [
                'themeSchema' => $oldThemeSchema,
                'timezone' => $oldTimezone,
            ],
            newValues: [
                'themeSchema' => $themeSchema,
                'timezone' => $timezone,
            ],
            tenantId: $user->tenant_id ? (int) $user->tenant_id : null
        );

        return $this->success([
            'themeSchema' => $themeSchema,
            'timezone' => $timezone,
        ], 'Preferences updated');
    }

    public function timezones(Request $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        return $this->success([
            'defaultTimezone' => ApiDateTime::defaultTimezone(),
            'records' => ApiDateTime::listTimezoneOptions(),
        ]);
    }
}
