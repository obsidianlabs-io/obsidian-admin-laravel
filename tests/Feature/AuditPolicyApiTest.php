<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_locale_update_audit_is_disabled_by_default(): void
    {
        $this->seed();

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $token = $this->loginAndGetToken('Admin');

        $response = $this->putJson('/api/auth/preferred-locale', [
            'locale' => 'zh-CN',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'user.locale.update',
            'user_id' => $adminUser->id,
        ]);
    }

    public function test_super_admin_can_list_global_audit_policies(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/audit/policy/list', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $records = $response->json('data.records');
        $this->assertIsArray($records);

        $localePolicy = collect($records)->firstWhere('action', 'user.locale.update');
        $this->assertNotNull($localePolicy);
        $this->assertSame(false, (bool) ($localePolicy['enabled'] ?? true));
    }

    public function test_non_super_admin_cannot_access_audit_policy_console(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/audit/policy/list', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_mandatory_audit_action_cannot_be_disabled(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->putJson('/api/audit/policy', [
            'records' => [
                [
                    'action' => 'user.create',
                    'enabled' => false,
                    'samplingRate' => 1,
                    'retentionDays' => 365,
                ],
            ],
            'changeReason' => 'Validate mandatory policy lock',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002');

        $this->assertStringContainsString('cannot be disabled', (string) $response->json('msg'));
    }

    public function test_super_admin_can_enable_optional_locale_audit_globally_for_all_tenants(): void
    {
        $this->seed();

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $adminBranchUser = User::query()->where('name', 'AdminBranch')->firstOrFail();
        $superToken = $this->loginAndGetToken('Super');

        $updateResponse = $this->putJson('/api/audit/policy', [
            'records' => [
                [
                    'action' => 'user.locale.update',
                    'enabled' => true,
                    'samplingRate' => 1,
                    'retentionDays' => 30,
                ],
            ],
            'changeReason' => 'Enable locale auditing for troubleshooting',
        ], [
            'Authorization' => 'Bearer '.$superToken,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000');

        // Force direct writes for this assertion path to avoid queue fakes/config
        // leakage from other tests swallowing the locale audit job.
        config()->set('audit.queue.enabled', false);

        $adminToken = $this->loginAndGetToken('Admin');
        $adminTargetLocale = $this->alternateLocaleForToken($adminToken);

        $localeResponse = $this->putJson('/api/auth/preferred-locale', [
            'locale' => $adminTargetLocale,
        ], [
            'Authorization' => 'Bearer '.$adminToken,
        ]);

        $localeResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.locale.update',
            'user_id' => $adminUser->id,
            'tenant_id' => (int) $adminUser->tenant_id,
        ]);

        $adminBranchToken = $this->loginAndGetToken('AdminBranch');
        $adminBranchTargetLocale = $this->alternateLocaleForToken($adminBranchToken);
        $branchLocaleResponse = $this->putJson('/api/auth/preferred-locale', [
            'locale' => $adminBranchTargetLocale,
        ], [
            'Authorization' => 'Bearer '.$adminBranchToken,
        ]);

        $branchLocaleResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.locale.update',
            'user_id' => $adminBranchUser->id,
            'tenant_id' => (int) $adminBranchUser->tenant_id,
        ]);

        $policyLogCount = AuditLog::query()->where('action', 'audit.policy.update')->count();
        $this->assertGreaterThan(0, $policyLogCount);
    }

    public function test_updating_policy_requires_change_reason(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $response = $this->putJson('/api/audit/policy', [
            'records' => [
                [
                    'action' => 'user.locale.update',
                    'enabled' => true,
                    'samplingRate' => 1,
                    'retentionDays' => 30,
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002');
    }

    public function test_super_admin_can_view_audit_policy_history(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Super');

        $updateResponse = $this->putJson('/api/audit/policy', [
            'records' => [
                [
                    'action' => 'user.locale.update',
                    'enabled' => true,
                    'samplingRate' => 1,
                    'retentionDays' => 30,
                ],
            ],
            'changeReason' => 'Enable locale update logging',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.revisionId', fn (mixed $id): bool => is_string($id) && $id !== '');

        $historyResponse = $this->getJson('/api/audit/policy/history?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $historyResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', fn (mixed $total): bool => is_int($total) && $total >= 1)
            ->assertJsonPath('data.records.0.changeReason', 'Enable locale update logging');
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

    private function alternateLocaleForToken(string $token): string
    {
        $response = $this->getJson('/api/auth/profile', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $currentLocale = trim((string) ($response->json('data.locale') ?? ''));

        return $currentLocale === 'zh-CN' ? 'en-US' : 'zh-CN';
    }
}
