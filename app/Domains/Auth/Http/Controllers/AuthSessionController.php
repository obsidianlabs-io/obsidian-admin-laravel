<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Access\Services\UserService;
use App\Domains\Auth\Actions\ResolveUserContextAction;
use App\Domains\Auth\Events\UserLoggedInEvent;
use App\Domains\Auth\Events\UserLoggedOutEvent;
use App\Domains\Auth\Http\Controllers\Concerns\ResolvesRoleScope;
use App\Domains\Auth\Http\Controllers\Concerns\ThrottlesLogins;
use App\Domains\Auth\Http\Controllers\Concerns\VerifiesTotpCode;
use App\Domains\Auth\Services\AuthSessionContextService;
use App\Domains\Auth\Services\AuthTokenService;
use App\Domains\Auth\Services\Results\TokenPairResult;
use App\Domains\Auth\Services\SessionClientContextData;
use App\Domains\Auth\Services\TotpService;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Services\AuditLogService;
use App\Domains\Tenant\Contracts\ActiveTenantResolver;
use App\DTOs\Auth\LoginInputDTO;
use App\DTOs\Auth\RefreshTokenInputDTO;
use App\DTOs\Auth\RegisterInputDTO;
use App\DTOs\Auth\RevokeSessionInputDTO;
use App\DTOs\Auth\UpdateSessionAliasInputDTO;
use App\DTOs\User\CreateUserDTO;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RefreshTokenRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\RevokeSessionRequest;
use App\Http\Requests\Api\Auth\UpdateSessionAliasRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthSessionController extends ApiController
{
    use ResolvesRoleScope;
    use ThrottlesLogins;
    use VerifiesTotpCode;

    public function __construct(
        private readonly UserService $userService,
        private readonly AuthTokenService $authTokenService,
        private readonly AuthSessionContextService $authSessionContextService,
        private readonly AuditLogService $auditLogService,
        protected readonly TotpService $totpService,
        private readonly ResolveUserContextAction $resolveUserContext,
        private readonly ActiveTenantResolver $activeTenantResolver
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $input = RegisterInputDTO::fromValidated($request->validated());

        $defaultTenantId = $this->activeTenantResolver->findActiveTenantIdByCode('TENANT_MAIN');
        $defaultRoleLookup = $this->findActiveRoleByCode('R_USER', $defaultTenantId);
        if ($defaultRoleLookup->failed()) {
            return $this->error(self::PARAM_ERROR_CODE, 'Default tenant role is not configured');
        }

        $defaultRoleModel = $defaultRoleLookup->requireRole();
        if (! $this->isRoleInTenantScope($defaultRoleModel, $defaultTenantId)) {
            return $this->error(self::PARAM_ERROR_CODE, 'Default tenant role is not configured');
        }

        $defaultRoleId = (int) $defaultRoleModel->id;

        return $this->withIdempotency($request, null, function () use ($input, $defaultRoleId, $defaultTenantId, $request): JsonResponse {
            $user = $this->userService->create(new CreateUserDTO(
                name: $input->name,
                email: $input->email,
                password: $input->password,
                status: '1',
                roleId: $defaultRoleId,
                tenantId: $defaultTenantId
            ));

            $tokens = $this->issueTokenPair($user, false, null, $request);

            $this->auditLogService->record(
                action: 'user.register',
                auditable: $user,
                actor: $user,
                request: $request,
                newValues: [
                    'userName' => $user->name,
                    'email' => $user->email,
                ],
                tenantId: $defaultTenantId
            );

            return $this->success($tokens->toArray(), 'Register success');
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $input = LoginInputDTO::fromValidated($request->validated());
        $throttleKey = $this->resolveLoginThrottleKey($request, $input->loginKey());
        if ($this->tooManyLoginAttempts($throttleKey)) {
            return $this->error(
                self::LOGIN_FAILED_CODE,
                sprintf('Too many login attempts. Try again in %d seconds.', $this->availableLoginThrottleSeconds($throttleKey))
            );
        }

        $loginKey = $input->loginKey();

        $user = User::query()
            ->where('name', $loginKey)
            ->orWhere('email', $loginKey)
            ->first();

        if (! $user || ! Hash::check($input->password, $user->password)) {
            $this->incrementLoginAttempts($throttleKey);

            return $this->error(self::LOGIN_FAILED_CODE, 'Username or password is incorrect');
        }

        if ($user->status !== '1') {
            $this->incrementLoginAttempts($throttleKey);

            return $this->error(self::UNAUTHORIZED_CODE, 'User is inactive');
        }

        if ($this->isTenantUserWithInactiveTenant($user)) {
            $this->incrementLoginAttempts($throttleKey);

            return $this->error(self::UNAUTHORIZED_CODE, 'Tenant is inactive');
        }

        if ($this->isUserWithInactiveRole($user)) {
            $this->incrementLoginAttempts($throttleKey);

            return $this->error(self::UNAUTHORIZED_CODE, 'Role is inactive');
        }

        if ((bool) config('security.require_email_verification', false) && ! $user->email_verified_at) {
            $this->incrementLoginAttempts($throttleKey);

            return $this->error(self::UNAUTHORIZED_CODE, 'Email is not verified');
        }

        $requiresTwoFactor = (bool) $user->two_factor_enabled
            || ((bool) config('security.super_admin_require_2fa', false) && $this->isSuperAdmin($user));
        if ($requiresTwoFactor) {
            $otpCode = $input->otpCode ?? '';
            if ($otpCode === '') {
                $this->clearLoginAttempts($throttleKey);

                return $this->error('4020', 'Two-factor code required');
            }
            if (! $this->verifyUserTotpCode($user, $otpCode)) {
                $this->incrementLoginAttempts($throttleKey);

                return $this->error(self::LOGIN_FAILED_CODE, 'Two-factor code is invalid');
            }
        }

        $this->clearLoginAttempts($throttleKey);
        $requestedLocale = $input->locale ?? '';
        if ($requestedLocale !== '') {
            $this->resolveUserContext->syncLocaleOnLogin($user, $requestedLocale);
        }

        $tokens = $this->issueTokenPair($user, $input->rememberMe, null, $request);

        event(UserLoggedInEvent::fromRequest($user, $request, $input->rememberMe));

        return $this->success($tokens->toArray(), 'Login success');
    }

    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        $input = RefreshTokenInputDTO::fromValidated($request->validated());
        $plainRefreshToken = $input->refreshToken;

        $token = PersonalAccessToken::findToken($plainRefreshToken);

        if (! $token || ! $token->can('refresh-token')) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Refresh token is invalid');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return $this->error(self::UNAUTHORIZED_CODE, 'Refresh token has expired');
        }

        $tokenable = $token->tokenable;
        if (! $tokenable instanceof User || $tokenable->status !== '1') {
            return $this->error(self::UNAUTHORIZED_CODE, 'Refresh token is invalid');
        }

        if ($this->isTenantUserWithInactiveTenant($tokenable)) {
            $token->delete();

            return $this->error(self::UNAUTHORIZED_CODE, 'Tenant is inactive');
        }

        if ($this->isUserWithInactiveRole($tokenable)) {
            $token->delete();

            return $this->error(self::UNAUTHORIZED_CODE, 'Role is inactive');
        }

        $sessionClientContext = $this->authTokenService->resolveSessionClientContextMetadata($token);

        // One-time refresh token usage.
        $token->delete();

        $rememberMe = $token->can('remember-me');
        $tokens = $this->issueTokenPair(
            $tokenable,
            $rememberMe,
            $this->authTokenService->resolveSessionId($token),
            $request,
            $sessionClientContext
        );

        return $this->success($tokens->toArray(), 'Refresh token success');
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessionContext = $this->resolveSessionContext($request);
        if ($sessionContext->failed()) {
            return $this->error($sessionContext->code(), $sessionContext->message());
        }

        $accessToken = $sessionContext->requireToken();
        $user = $sessionContext->requireUser();

        $records = $this->authSessionContextService->mapSessionRecordsForResponse(
            $this->authTokenService->listSessions($user, $accessToken),
            $request
        );

        return $this->success([
            'singleDeviceLogin' => (bool) config('security.auth_tokens.single_device_login', true),
            'records' => $records,
        ]);
    }

    public function updateSessionAlias(UpdateSessionAliasRequest $request, string $sessionId): JsonResponse
    {
        $sessionContext = $this->resolveSessionContext($request);
        if ($sessionContext->failed()) {
            return $this->error($sessionContext->code(), $sessionContext->message());
        }

        $accessToken = $sessionContext->requireToken();
        $user = $sessionContext->requireUser();
        $input = UpdateSessionAliasInputDTO::fromValidated($request->validated());
        $validatedSessionId = $input->sessionId;

        $result = $this->authTokenService->updateSessionAlias(
            $user,
            $validatedSessionId,
            $input->deviceAlias,
            $accessToken
        );

        if ($result->updatedTokenCount() <= 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Session not found');
        }

        return $this->success([
            'sessionId' => $validatedSessionId,
            'deviceAlias' => (string) ($result->deviceAlias() ?? ''),
            'updatedTokenCount' => $result->updatedTokenCount(),
            'updatedCurrentSession' => $result->updatedCurrentSession(),
        ], 'Session alias updated');
    }

    public function revokeSession(RevokeSessionRequest $request, string $sessionId): JsonResponse
    {
        $sessionContext = $this->resolveSessionContext($request);
        if ($sessionContext->failed()) {
            return $this->error($sessionContext->code(), $sessionContext->message());
        }

        $accessToken = $sessionContext->requireToken();
        $user = $sessionContext->requireUser();
        $input = RevokeSessionInputDTO::fromValidated($request->validated());
        $validatedSessionId = $input->sessionId;

        $result = $this->authTokenService->revokeSession($user, $validatedSessionId, $accessToken);

        if ($result->deletedTokenCount() <= 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Session not found');
        }

        if ($result->revokedCurrentSession()) {
            event(UserLoggedOutEvent::fromRequest($user, $request));
        }

        return $this->success([
            'sessionId' => $validatedSessionId,
            'deletedTokenCount' => $result->deletedTokenCount(),
            'revokedCurrentSession' => $result->revokedCurrentSession(),
        ], 'Session revoked');
    }

    public function logout(Request $request): JsonResponse
    {
        $sessionContext = $this->resolveSessionContext($request);
        if ($sessionContext->failed()) {
            return $this->error($sessionContext->code(), $sessionContext->message());
        }

        $accessToken = $sessionContext->requireToken();
        $user = $sessionContext->requireUser();

        $sessionId = $this->authTokenService->resolveSessionId($accessToken);

        if ($sessionId !== null) {
            $this->authTokenService->revokeSession($user, $sessionId, $accessToken);
        } else {
            $accessToken->delete();

            $refreshToken = $request->input('refreshToken');
            if (is_string($refreshToken) && $refreshToken !== '') {
                $refreshTokenRecord = PersonalAccessToken::findToken($refreshToken);
                if (
                    $refreshTokenRecord
                    && $refreshTokenRecord->tokenable_type === User::class
                    && $refreshTokenRecord->tokenable_id === $user->id
                ) {
                    $refreshTokenRecord->delete();
                }
            }
        }

        event(UserLoggedOutEvent::fromRequest($user, $request));

        return $this->success([
            'userId' => (string) $user->id,
        ], 'Logout success');
    }

    public function customError(Request $request): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        if (! $this->isSuperAdmin($user)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $code = (string) $request->query('code', '1001');
        $msg = (string) $request->query('msg', 'Custom backend error');

        return $this->error($code, $msg);
    }

    /**
     * Issue access + refresh token pair with merged session client context.
     */
    private function issueTokenPair(
        User $user,
        bool $rememberMe = false,
        ?string $sessionId = null,
        ?Request $request = null,
        ?SessionClientContextData $baseSessionClientContext = null
    ): TokenPairResult {
        $requestSessionClientContext = $this->authTokenService->buildSessionClientContext(
            $request?->userAgent(),
            $request?->ip()
        );

        $sessionClientContext = $baseSessionClientContext?->merge($requestSessionClientContext)
            ?? $requestSessionClientContext;

        return $this->authTokenService->issueTokenPair($user, $rememberMe, $sessionId, $sessionClientContext);
    }

    private function resolveSessionContext(Request $request): ApiAuthResult
    {
        return $this->authSessionContextService->requireAuthenticatedSession(
            $this->authenticate($request, 'access-api')
        );
    }

    private function resolveAuthenticatedUser(Request $request): ApiAuthResult
    {
        return $this->authenticate($request, 'access-api');
    }

    private function isTenantUserWithInactiveTenant(User $user): bool
    {
        $tenantId = $user->tenant_id ? (int) $user->tenant_id : 0;
        if ($tenantId <= 0) {
            return false;
        }

        $user->loadMissing('tenant:id,status');

        if (! $user->tenant) {
            return true;
        }

        return (string) $user->tenant->status !== '1';
    }

    private function isUserWithInactiveRole(User $user): bool
    {
        $user->loadMissing('role:id,code,level,status,tenant_id');

        if (! $user->role) {
            return true;
        }

        return (string) $user->role->status !== '1';
    }
}
