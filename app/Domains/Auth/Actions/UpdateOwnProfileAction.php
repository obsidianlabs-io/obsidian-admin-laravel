<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Access\Models\UserPreference;
use App\Domains\Access\Services\UserService;
use App\Domains\Auth\Actions\Results\UpdateOwnProfileResult;
use App\DTOs\User\UpdateOwnProfileDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Support\ApiDateTime;
use Illuminate\Support\Facades\DB;

final class UpdateOwnProfileAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ResolveUserContextAction $resolveUserContext
    ) {}

    public function handle(User $user, UpdateOwnProfileDTO $dto): UpdateOwnProfileResult
    {
        $oldThemeSchema = $this->resolveUserContext->resolveThemeSchema($user);
        $oldTimezone = $this->resolveUserContext->resolveTimezone($user);
        $nextTimezone = $dto->timezone !== null
            ? ApiDateTime::normalizeTimezone($dto->timezone)
            : $oldTimezone;

        $oldValues = [
            'userName' => (string) $user->name,
            'email' => (string) $user->email,
            'timezone' => $oldTimezone,
            'themeSchema' => $oldThemeSchema,
        ];

        DB::transaction(function () use ($user, $dto, $nextTimezone, $oldTimezone): void {
            $this->userService->update($user, new UpdateUserDTO(
                name: $dto->userName,
                email: $dto->email,
                password: $dto->password,
                status: (string) $user->status,
                roleId: (int) $user->role_id,
                tenantId: $user->tenant_id ? (int) $user->tenant_id : null,
                organizationId: $user->organization_id ? (int) $user->organization_id : null,
                teamId: $user->team_id ? (int) $user->team_id : null,
            ));

            if ($dto->timezone !== null && $nextTimezone !== $oldTimezone) {
                UserPreference::query()->updateOrCreate(
                    ['user_id' => (int) $user->id],
                    ['timezone' => $nextTimezone]
                );
            }
        });

        $user->refresh();
        $this->resolveUserContext->invalidateUserContextCache();

        $newValues = [
            'userName' => (string) $user->name,
            'email' => (string) $user->email,
            'timezone' => $nextTimezone,
            'themeSchema' => $this->resolveUserContext->resolveThemeSchema($user),
        ];

        return UpdateOwnProfileResult::success($oldValues, $newValues);
    }
}
