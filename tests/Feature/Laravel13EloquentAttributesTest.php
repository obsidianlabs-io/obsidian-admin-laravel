<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Domains\System\Models\AuditPolicy;
use App\Domains\Tenant\Models\Tenant;
use App\Policies\AuditLogPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Laravel13EloquentAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_boot_attribute_keeps_tenant_scope_id_in_sync(): void
    {
        $tenant = Tenant::query()->create([
            'code' => 'tenant-attribute-user',
            'name' => 'Tenant Attribute User',
            'status' => '1',
        ]);

        $user = new User([
            'name' => 'Attribute User',
            'email' => 'attribute-user@example.test',
            'password' => 'secret',
            'status' => '1',
            'tenant_id' => $tenant->id,
        ]);

        $user->save();

        $this->assertSame($tenant->id, $user->tenant_scope_id);
    }

    public function test_role_boot_attribute_keeps_tenant_scope_id_in_sync(): void
    {
        $tenant = Tenant::query()->create([
            'code' => 'tenant-attribute-role',
            'name' => 'Tenant Attribute Role',
            'status' => '1',
        ]);

        $role = new Role([
            'code' => 'ROLE_ATTR',
            'name' => 'Role Attribute',
            'status' => '1',
            'level' => 10,
            'tenant_id' => $tenant->id,
        ]);

        $role->save();

        $this->assertSame($tenant->id, $role->tenant_scope_id);
    }

    public function test_audit_policy_boot_attribute_defaults_platform_scope(): void
    {
        $policy = new AuditPolicy([
            'action' => 'user.create',
            'is_mandatory' => true,
            'enabled' => true,
            'sampling_rate' => 1,
            'retention_days' => 90,
        ]);

        $policy->save();

        $this->assertSame(AuditPolicy::PLATFORM_SCOPE_ID, $policy->tenant_scope_id);
    }

    public function test_gate_resolves_model_policies_from_use_policy_attributes(): void
    {
        /** @var Gate $gate */
        $gate = app(Gate::class);

        $this->assertInstanceOf(UserPolicy::class, $gate->getPolicyFor(User::class));
        $this->assertInstanceOf(RolePolicy::class, $gate->getPolicyFor(Role::class));
        $this->assertInstanceOf(TenantPolicy::class, $gate->getPolicyFor(Tenant::class));
        $this->assertInstanceOf(AuditLogPolicy::class, $gate->getPolicyFor(AuditLog::class));
    }
}
