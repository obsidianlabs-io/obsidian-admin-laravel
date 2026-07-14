<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function create(string $name, string $email, string $password, string $status, int $roleId, ?int $tenantId, ?int $organizationId = null, ?int $teamId = null, ?AuditContext $audit = null): User
    {
        $user = DB::transaction(function () use ($name, $email, $password, $status, $roleId, $tenantId, $organizationId, $teamId): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'status' => $status,
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'organization_id' => $organizationId,
                'team_id' => $teamId,
                'tenant_scope_id' => $tenantId ?? 0,
            ]);

            $user->preference()->create([
                'timezone' => 'Asia/Kuala_Lumpur',
            ]);

            return $user;
        });

        $this->apiCacheService->bump('users');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $user, $tenantId) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'user.create',
                    auditable: $user,
                    actor: $audit->actor,
                    newValues: [
                        'userName' => $user->name,
                        'email' => $user->email,
                        'roleCode' => $user->role_id ? Role::query()->whereKey((int) $user->role_id)->value('code') : '',
                        'status' => (string) $user->status,
                        'organizationId' => $user->organization_id !== null ? (int) $user->organization_id : null,
                        'teamId' => $user->team_id !== null ? (int) $user->team_id : null,
                    ],
                    tenantId: $tenantId,
                ));
            });
        }

        return $user;
    }

    public function update(User $user, string $name, string $email, ?string $password, string $status, int $roleId, ?int $tenantId, ?int $organizationId = null, ?int $teamId = null, ?AuditContext $audit = null): User
    {
        $updated = DB::transaction(function () use ($user, $name, $email, $password, $status, $roleId, $tenantId, $organizationId, $teamId): User {
            $payload = [
                'name' => $name,
                'email' => $email,
                'status' => $status,
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'organization_id' => $organizationId,
                'team_id' => $teamId,
                'tenant_scope_id' => $tenantId ?? 0,
            ];

            if ($password !== null) {
                $payload['password'] = $password;
            }

            $user->forceFill($payload)->save();

            return $user;
        });

        $this->apiCacheService->bump('users');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'user.update',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: [
                        'userName' => $updated->name,
                        'email' => $updated->email,
                        'roleCode' => $updated->role_id ? Role::query()->whereKey((int) $updated->role_id)->value('code') : '',
                        'status' => (string) $updated->status,
                        'organizationId' => $updated->organization_id !== null ? (int) $updated->organization_id : null,
                        'teamId' => $updated->team_id !== null ? (int) $updated->team_id : null,
                    ],
                    tenantId: $updated->tenant_id !== null ? (int) $updated->tenant_id : null,
                ));
            });
        }

        return $updated;
    }

    public function delete(User $user, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        $this->apiCacheService->bump('users');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $user) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'user.soft_delete',
                    auditable: $user,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    tenantId: $user->tenant_id !== null ? (int) $user->tenant_id : null,
                ));
            });
        }
    }

    public function deactivate(User $user, ?AuditContext $audit = null): User
    {
        $updated = DB::transaction(function () use ($user): User {
            $user->tokens()->delete();

            if ((string) $user->status !== '2') {
                $user->forceFill(['status' => '2'])->save();
            }

            return $user;
        });

        $this->apiCacheService->bump('users');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'user.deactivate',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: [
                        'userName' => $updated->name,
                        'email' => $updated->email,
                        'roleCode' => $updated->role_id ? Role::query()->whereKey((int) $updated->role_id)->value('code') : '',
                        'status' => (string) $updated->status,
                    ],
                    tenantId: $updated->tenant_id !== null ? (int) $updated->tenant_id : null,
                ));
            });
        }

        return $updated;
    }

    public function assignRole(User $user, Role $role, ?AuditContext $audit = null): User
    {
        $updated = DB::transaction(function () use ($user, $role): User {
            $user->forceFill(['role_id' => $role->id])->save();

            return $user;
        });

        $this->apiCacheService->bump('users');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'user.assign_role',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: $audit->newValues,
                    tenantId: $updated->tenant_id !== null ? (int) $updated->tenant_id : null,
                ));
            });
        }

        return $updated;
    }

    /**
     * Resolve the role code for a user, preferring the eager-loaded relation
     * and falling back to a direct lookup when the relation is not loaded.
     */
    public function resolveRoleCode(User $user): string
    {
        $role = $user->getRelationValue('role');
        if ($role instanceof Role) {
            $attributes = $role->getAttributes();
            $code = $attributes['code'] ?? null;
            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        $roleCode = $user->role_id
            ? Role::query()->whereKey((int) $user->role_id)->value('code')
            : null;

        return is_string($roleCode) ? $roleCode : '';
    }
}
