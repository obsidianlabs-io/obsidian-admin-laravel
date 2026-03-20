<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Laravel13EloquentScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_scope_attributes_filter_by_status_tenant_and_level(): void
    {
        $tenant = Tenant::query()->create([
            'code' => 'tenant-scope-role-main',
            'name' => 'Tenant Scope Role Main',
            'status' => '1',
        ]);
        $otherTenant = Tenant::query()->create([
            'code' => 'tenant-scope-role-other',
            'name' => 'Tenant Scope Role Other',
            'status' => '1',
        ]);

        Role::query()->create([
            'code' => 'R_KEEP',
            'name' => 'Keep',
            'status' => '1',
            'level' => 20,
            'tenant_id' => $tenant->id,
        ]);
        Role::query()->create([
            'code' => 'R_INACTIVE',
            'name' => 'Inactive',
            'status' => '2',
            'level' => 10,
            'tenant_id' => $tenant->id,
        ]);
        Role::query()->create([
            'code' => 'R_TOO_HIGH',
            'name' => 'Too High',
            'status' => '1',
            'level' => 50,
            'tenant_id' => $tenant->id,
        ]);
        Role::query()->create([
            'code' => 'R_OTHER_TENANT',
            'name' => 'Other Tenant',
            'status' => '1',
            'level' => 10,
            'tenant_id' => $otherTenant->id,
        ]);

        $codes = Role::query()
            ->active()
            ->inTenantScope($tenant->id)
            ->upToLevel(30)
            ->orderBy('id')
            ->pluck('code')
            ->all();

        $this->assertSame(['R_KEEP'], $codes);
    }

    public function test_user_scope_attributes_filter_visible_users_for_actor_level_and_role_code(): void
    {
        $tenant = Tenant::query()->create([
            'code' => 'tenant-scope-user-main',
            'name' => 'Tenant Scope User Main',
            'status' => '1',
        ]);

        $actorRole = Role::query()->create([
            'code' => 'R_ACTOR',
            'name' => 'Actor Role',
            'status' => '1',
            'level' => 20,
            'tenant_id' => $tenant->id,
        ]);
        $visibleRole = Role::query()->create([
            'code' => 'R_VISIBLE',
            'name' => 'Visible Role',
            'status' => '1',
            'level' => 10,
            'tenant_id' => $tenant->id,
        ]);
        $hiddenRole = Role::query()->create([
            'code' => 'R_HIDDEN',
            'name' => 'Hidden Role',
            'status' => '1',
            'level' => 40,
            'tenant_id' => $tenant->id,
        ]);

        $actor = User::query()->create([
            'name' => 'Scope Actor',
            'email' => 'scope-actor@example.test',
            'password' => 'secret',
            'status' => '1',
            'role_id' => $actorRole->id,
            'tenant_id' => $tenant->id,
        ]);
        User::query()->create([
            'name' => 'Scope Visible',
            'email' => 'scope-visible@example.test',
            'password' => 'secret',
            'status' => '1',
            'role_id' => $visibleRole->id,
            'tenant_id' => $tenant->id,
        ]);
        User::query()->create([
            'name' => 'Scope Hidden',
            'email' => 'scope-hidden@example.test',
            'password' => 'secret',
            'status' => '1',
            'role_id' => $hiddenRole->id,
            'tenant_id' => $tenant->id,
        ]);
        User::query()->create([
            'name' => 'Scope No Role',
            'email' => 'scope-no-role@example.test',
            'password' => 'secret',
            'status' => '1',
            'tenant_id' => $tenant->id,
        ]);

        $emails = User::query()
            ->excludingUser($actor->id)
            ->visibleToActorLevel(20)
            ->withRoleCode('R_VISIBLE')
            ->orderBy('id')
            ->pluck('email')
            ->all();

        $this->assertSame(['scope-visible@example.test'], $emails);
    }
}
