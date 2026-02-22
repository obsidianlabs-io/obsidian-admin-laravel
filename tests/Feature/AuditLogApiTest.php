<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_only_current_tenant_audit_logs(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();
        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'user.update',
            'auditable_type' => User::class,
            'auditable_id' => $adminUser->id,
            'new_values' => ['status' => '1'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $branchTenant->id,
            'action' => 'user.update',
            'auditable_type' => User::class,
            'auditable_id' => $adminUser->id,
            'new_values' => ['status' => '2'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/audit/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.tenantId', (string) $mainTenant->id);
    }

    public function test_user_without_audit_permission_cannot_access_audit_log_list(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('User');

        $response = $this->getJson('/api/audit/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_super_admin_without_selected_tenant_can_only_view_platform_audit_logs(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $superUser = User::query()->where('name', 'Super')->firstOrFail();

        AuditLog::query()->create([
            'user_id' => $superUser->id,
            'tenant_id' => null,
            'action' => 'system.config.update',
            'auditable_type' => 'system',
            'auditable_id' => null,
            'new_values' => ['key' => 'x'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        AuditLog::query()->create([
            'user_id' => $superUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'user.create',
            'auditable_type' => User::class,
            'auditable_id' => $superUser->id,
            'new_values' => ['name' => 'Demo'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/audit/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.tenantId', '')
            ->assertJsonPath('data.records.0.tenantName', 'No Tenant');
    }

    public function test_super_admin_with_selected_tenant_can_view_selected_tenant_audit_logs(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $superUser = User::query()->where('name', 'Super')->firstOrFail();

        AuditLog::query()->create([
            'user_id' => $superUser->id,
            'tenant_id' => null,
            'action' => 'system.config.update',
            'auditable_type' => 'system',
            'auditable_id' => null,
            'new_values' => ['key' => 'x'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        AuditLog::query()->create([
            'user_id' => $superUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'user.create',
            'auditable_type' => User::class,
            'auditable_id' => $superUser->id,
            'new_values' => ['name' => 'Demo'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/audit/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.tenantId', (string) $mainTenant->id);
    }

    private function loginAndGetToken(string $userName): string
    {
        $response = $this->postJson('/api/auth/login', [
            'userName' => $userName,
            'password' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        return (string) $response->json('data.token');
    }
}
