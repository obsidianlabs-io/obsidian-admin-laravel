<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Domains\System\Models\AuditPolicy;
use App\Domains\System\Services\AuditLogService;
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

    public function test_admin_can_filter_audit_logs_by_log_type(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'role.update',
            'log_type' => 'permission',
            'auditable_type' => 'role',
            'auditable_id' => 1,
            'new_values' => ['name' => 'Manager'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'tenant.update',
            'log_type' => 'data',
            'auditable_type' => Tenant::class,
            'auditable_id' => $mainTenant->id,
            'new_values' => ['name' => 'Tenant Main'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/audit/list?current=1&size=20&logType=permission', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.action', 'role.update')
            ->assertJsonPath('data.records.0.logType', 'permission');
    }

    public function test_admin_can_filter_audit_logs_by_request_id(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'user.update',
            'log_type' => 'data',
            'auditable_type' => User::class,
            'auditable_id' => $adminUser->id,
            'new_values' => ['status' => '1'],
            'request_id' => 'req-abc-123',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'user.update',
            'log_type' => 'data',
            'auditable_type' => User::class,
            'auditable_id' => $adminUser->id,
            'new_values' => ['status' => '1'],
            'request_id' => 'req-other',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/audit/list?current=1&size=20&requestId=req-abc', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.requestId', 'req-abc-123');
    }

    public function test_audit_log_list_defaults_to_last_7_days_when_no_date_filters_provided(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        $recent = AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'user.update',
            'log_type' => 'data',
            'auditable_type' => User::class,
            'auditable_id' => $adminUser->id,
            'new_values' => ['status' => '1'],
            'request_id' => 'req-recent',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $old = AuditLog::query()->create([
            'user_id' => $adminUser->id,
            'tenant_id' => $mainTenant->id,
            'action' => 'user.update',
            'log_type' => 'data',
            'auditable_type' => User::class,
            'auditable_id' => $adminUser->id,
            'new_values' => ['status' => '1'],
            'request_id' => 'req-old',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $recent->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();
        $old->forceFill([
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(12),
        ])->save();

        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/audit/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.requestId', 'req-recent');
    }

    public function test_audit_log_service_assigns_log_type_for_permission_action(): void
    {
        $this->seed();
        config()->set('audit.queue.enabled', false);

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        // Ensure policy allows recording in case this action was overridden.
        AuditPolicy::query()->updateOrCreate(
            [
                'tenant_scope_id' => $adminUser->tenant_id,
                'action' => 'role.update',
            ],
            [
                'tenant_id' => $adminUser->tenant_id,
                'is_mandatory' => true,
                'enabled' => true,
                'sampling_rate' => 1.0,
                'retention_days' => 365,
            ]
        );

        app(AuditLogService::class)->record(
            action: 'role.update',
            auditable: 'role',
            actor: $adminUser,
            request: null,
            oldValues: ['name' => 'Old Role'],
            newValues: ['name' => 'New Role'],
            tenantId: (int) $adminUser->tenant_id
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $adminUser->id,
            'tenant_id' => (int) $adminUser->tenant_id,
            'action' => 'role.update',
            'log_type' => 'permission',
        ]);
    }

    public function test_audit_log_service_masks_sensitive_payload_fields(): void
    {
        $this->seed();
        config()->set('audit.queue.enabled', false);
        config()->set('audit.payload.max_json_bytes', 10000);
        config()->set('audit.payload.redacted_text', '[REDACTED]');

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        app(AuditLogService::class)->record(
            action: 'user.update',
            auditable: $adminUser,
            actor: $adminUser,
            request: null,
            oldValues: [
                'password' => 'old-password',
                'token' => 'old-token',
            ],
            newValues: [
                'password' => 'new-password',
                'profile' => [
                    'api_key' => 'secret-key',
                ],
            ],
            tenantId: (int) $adminUser->tenant_id
        );

        /** @var AuditLog $record */
        $record = AuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame('[REDACTED]', (string) ($record->old_values['password'] ?? ''));
        $this->assertSame('[REDACTED]', (string) ($record->old_values['token'] ?? ''));
        $this->assertSame('[REDACTED]', (string) (($record->new_values['profile']['api_key'] ?? null)));
    }

    public function test_audit_log_service_truncates_oversized_payload(): void
    {
        $this->seed();
        config()->set('audit.queue.enabled', false);
        config()->set('audit.payload.max_json_bytes', 256);

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        app(AuditLogService::class)->record(
            action: 'user.update',
            auditable: $adminUser,
            actor: $adminUser,
            request: null,
            oldValues: [],
            newValues: [
                'note' => str_repeat('x', 2000),
            ],
            tenantId: (int) $adminUser->tenant_id
        );

        /** @var AuditLog $record */
        $record = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertTrue((bool) ($record->new_values['_truncated'] ?? false));
        $this->assertSame('payload_oversize', (string) ($record->new_values['_reason'] ?? ''));
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
