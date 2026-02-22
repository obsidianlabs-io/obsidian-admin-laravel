<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use App\Jobs\WriteAuditLogJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformHardeningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_contains_request_id_in_header_and_payload(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');
        $response = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertHeader('X-Request-Id')
            ->assertHeader('X-Trace-Id')
            ->assertHeader('traceparent');

        $headerRequestId = (string) $response->headers->get('X-Request-Id', '');
        $this->assertNotSame('', $headerRequestId);
        $this->assertSame($headerRequestId, (string) $response->json('requestId'));

        $headerTraceId = (string) $response->headers->get('X-Trace-Id', '');
        $this->assertNotSame('', $headerTraceId);
        $this->assertSame($headerTraceId, (string) $response->json('traceId'));

        $traceParent = (string) $response->headers->get('traceparent', '');
        $this->assertMatchesRegularExpression('/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/', $traceParent);
    }

    public function test_versioned_v1_routes_are_available(): void
    {
        $this->seed();

        $login = $this->postJson('/api/v1/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $login->assertOk()
            ->assertJsonPath('code', '0000');

        $token = (string) $login->json('data.token');
        $this->assertNotSame('', $token);

        $userInfo = $this->getJson('/api/v1/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $userInfo->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.userName', 'Admin');
    }

    public function test_create_tenant_is_idempotent_when_idempotency_key_is_reused(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'tenant-create-repeatable-1',
        ];
        $payload = [
            'tenantCode' => 'TENANT_IDEMPOTENT',
            'tenantName' => 'Idempotent Tenant',
            'status' => '1',
        ];

        $first = $this->postJson('/api/tenant', $payload, $headers);
        $second = $this->postJson('/api/tenant', $payload, $headers);

        $first->assertOk()
            ->assertJsonPath('code', '0000');
        $second->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertHeader('X-Idempotent-Replay', '1');

        $secondTraceId = (string) $second->headers->get('X-Trace-Id', '');
        $this->assertNotSame('', $secondTraceId);
        $this->assertSame($secondTraceId, (string) $second->json('traceId'));

        $this->assertSame((string) $first->json('data.id'), (string) $second->json('data.id'));
        $this->assertSame(1, Tenant::query()->where('code', 'TENANT_IDEMPOTENT')->count());
    }

    public function test_update_tenant_is_idempotent_when_idempotency_key_is_reused(): void
    {
        $this->seed();

        $tenant = Tenant::query()->create([
            'code' => 'TENANT_IDEMPOTENT_UPDATE',
            'name' => 'Before Idempotent Update',
            'status' => '1',
        ]);
        $tenant->refresh();

        $token = $this->loginAndGetToken('Super');
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'tenant-update-repeatable-1',
        ];
        $payload = [
            'tenantCode' => 'TENANT_IDEMPOTENT_UPDATE',
            'tenantName' => 'After Idempotent Update',
            'status' => '1',
            'version' => (string) ($tenant->updated_at?->timestamp ?? 0),
        ];

        $first = $this->putJson('/api/tenant/'.$tenant->id, $payload, $headers);
        $second = $this->putJson('/api/tenant/'.$tenant->id, $payload, $headers);

        $first->assertOk()
            ->assertJsonPath('code', '0000');
        $second->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertHeader('X-Idempotent-Replay', '1');

        $secondTraceId = (string) $second->headers->get('X-Trace-Id', '');
        $this->assertNotSame('', $secondTraceId);
        $this->assertSame($secondTraceId, (string) $second->json('traceId'));

        $tenant->refresh();
        $this->assertSame('After Idempotent Update', (string) $tenant->name);
        $this->assertSame((string) $first->json('data.version'), (string) $second->json('data.version'));
    }

    public function test_update_tenant_returns_conflict_when_optimistic_lock_token_is_stale(): void
    {
        $this->seed();

        $tenant = Tenant::query()->create([
            'code' => 'TENANT_OPT_LOCK',
            'name' => 'Tenant Before Lock',
            'status' => '1',
        ]);

        $token = $this->loginAndGetToken('Super');
        $response = $this->putJson('/api/tenant/'.$tenant->id, [
            'tenantCode' => 'TENANT_OPT_LOCK',
            'tenantName' => 'Tenant Should Not Update',
            'status' => '1',
            'version' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1009')
            ->assertJsonPath('msg', 'Tenant has been modified by another user. Please refresh and retry.');

        $tenant->refresh();
        $this->assertSame('Tenant Before Lock', (string) $tenant->name);
    }

    public function test_user_list_supports_cursor_pagination_mode(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $mainUserRole = Role::query()
            ->where('code', 'R_USER')
            ->where('tenant_id', $mainTenant->id)
            ->firstOrFail();

        for ($index = 1; $index <= 4; $index++) {
            User::query()->create([
                'name' => 'CursorUser'.$index,
                'email' => 'cursor.user.'.$index.'@obsidian.local',
                'password' => 'Aa123456',
                'status' => '1',
                'role_id' => $mainUserRole->id,
                'tenant_id' => $mainTenant->id,
            ]);
        }

        $token = $this->loginAndGetToken('Admin');

        $firstPage = $this->getJson('/api/user/list?size=2&paginationMode=cursor&cursor=', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $firstPage->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.paginationMode', 'cursor')
            ->assertJsonPath('data.hasMore', true);

        $firstRecords = $firstPage->json('data.records');
        $this->assertIsArray($firstRecords);
        $this->assertCount(2, $firstRecords);

        $nextCursor = (string) $firstPage->json('data.nextCursor');
        $this->assertNotSame('', $nextCursor);

        $secondPage = $this->getJson('/api/user/list?size=2&cursor='.$nextCursor, [
            'Authorization' => 'Bearer '.$token,
        ]);
        $secondPage->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.paginationMode', 'cursor');

        $secondRecords = $secondPage->json('data.records');
        $this->assertIsArray($secondRecords);
        $this->assertNotEmpty($secondRecords);

        $firstIds = array_map(static fn (array $record): string => (string) ($record['userId'] ?? $record['id'] ?? ''), $firstRecords);
        $secondIds = array_map(static fn (array $record): string => (string) ($record['userId'] ?? $record['id'] ?? ''), $secondRecords);
        $this->assertEmpty(array_intersect($firstIds, $secondIds));
    }

    public function test_super_admin_without_tenant_scope_cannot_manage_tenant_users_by_direct_url(): void
    {
        $this->seed();

        $tenantAdmin = User::query()->where('name', 'Admin')->firstOrFail();
        $token = $this->loginAndGetToken('Super');

        $response = $this->deleteJson('/api/user/'.$tenantAdmin->id, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');

        $this->assertNull(User::query()->withTrashed()->findOrFail($tenantAdmin->id)->deleted_at);
    }

    public function test_permission_cache_is_invalidated_after_permission_creation(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');
        $headers = ['Authorization' => 'Bearer '.$token];

        $firstAllResponse = $this->getJson('/api/permission/all', $headers);
        $firstAllResponse->assertOk()->assertJsonPath('code', '0000');

        $createResponse = $this->postJson('/api/permission', [
            'permissionCode' => 'report.cursor.view',
            'permissionName' => 'View Cursor Reports',
            'description' => 'Permission created during cache test',
            'status' => '1',
        ], $headers);

        $createResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $secondAllResponse = $this->getJson('/api/permission/all', $headers);
        $secondAllResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $permissionCodes = collect($secondAllResponse->json('data.records'))
            ->map(static fn (array $record): string => (string) ($record['permissionCode'] ?? ''))
            ->all();

        $this->assertContains('report.cursor.view', $permissionCodes);
    }

    public function test_traceparent_header_is_propagated_with_same_trace_id(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');
        $incomingTraceParent = '00-11111111111111111111111111111111-2222222222222222-01';

        $response = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
            'traceparent' => $incomingTraceParent,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertHeader('traceparent')
            ->assertHeader('X-Trace-Id', '11111111111111111111111111111111');

        $returnedTraceParent = (string) $response->headers->get('traceparent', '');
        $this->assertStringStartsWith('00-11111111111111111111111111111111-', $returnedTraceParent);
    }

    public function test_audit_logs_are_dispatched_through_queue_when_enabled(): void
    {
        $this->seed();

        config()->set('audit.queue.enabled', true);
        Queue::fake();

        $token = $this->loginAndGetToken('Super');
        $response = $this->postJson('/api/tenant', [
            'tenantCode' => 'TENANT_AUDIT_QUEUE',
            'tenantName' => 'Audit Queue Tenant',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        Queue::assertPushed(WriteAuditLogJob::class);
    }

    public function test_health_endpoint_exposes_runtime_checks(): void
    {
        $this->seed();

        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'name',
                'service',
                'status',
                'timestamp',
                'requestId',
                'traceId',
                'context' => ['environment', 'timezone', 'database', 'cache_store', 'queue_connection', 'log_channel'],
                'checks',
            ]);

        $status = (string) $response->json('status');
        $this->assertContains($status, ['ok', 'warn', 'fail']);
    }

    public function test_health_liveness_endpoint_is_available(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertOk()
            ->assertJsonPath('status', 'alive')
            ->assertJsonStructure(['name', 'service', 'status', 'timestamp', 'requestId', 'traceId']);
    }

    public function test_health_readiness_endpoint_reports_ready_state(): void
    {
        $this->seed();

        $response = $this->getJson('/api/health/ready');

        $response->assertOk()
            ->assertJsonStructure([
                'name',
                'service',
                'status',
                'ready',
                'timestamp',
                'requestId',
                'traceId',
                'context',
                'checks',
            ]);

        $this->assertTrue((bool) $response->json('ready'));
        $this->assertSame('ready', (string) $response->json('status'));
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
