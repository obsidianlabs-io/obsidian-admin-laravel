<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_prune_removes_expired_logs_by_policy_defaults(): void
    {
        $this->seed();

        $tenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $user = User::query()->where('name', 'Admin')->firstOrFail();

        $expiredLocaleLog = $this->createAuditLog($user, $tenant->id, 'user.locale.update', now()->subDays(45));
        $activeLocaleLog = $this->createAuditLog($user, $tenant->id, 'user.locale.update', now()->subDays(10));
        $expiredMandatoryLog = $this->createAuditLog($user, $tenant->id, 'user.create', now()->subDays(400));
        $activeMandatoryLog = $this->createAuditLog($user, $tenant->id, 'user.create', now()->subDays(20));

        $this->artisan('audit:prune')
            ->expectsOutputToContain('Total deleted:')
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $expiredLocaleLog->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $activeLocaleLog->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $expiredMandatoryLog->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $activeMandatoryLog->id]);
    }

    public function test_audit_prune_dry_run_does_not_delete_logs(): void
    {
        $this->seed();

        $tenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $user = User::query()->where('name', 'Admin')->firstOrFail();
        $expiredLog = $this->createAuditLog($user, $tenant->id, 'user.locale.update', now()->subDays(60));

        $this->artisan('audit:prune --dry-run')
            ->expectsOutputToContain('[DRY-RUN] Audit prune completed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $expiredLog->id]);
    }

    private function createAuditLog(User $user, ?int $tenantId, string $action, \Illuminate\Support\Carbon $createdAt): AuditLog
    {
        $log = AuditLog::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => ['status' => '1'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $log->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $log;
    }
}
