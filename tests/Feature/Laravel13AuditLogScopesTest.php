<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\System\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Laravel13AuditLogScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_scope_attributes_filter_list_queries(): void
    {
        $matching = AuditLog::query()->create([
            'action' => 'user.update',
            'log_type' => AuditLog::LOG_TYPE_DATA,
            'auditable_type' => 'user',
            'auditable_id' => 1,
            'request_id' => 'req-match-001',
        ]);

        AuditLog::query()->create([
            'action' => 'user.update',
            'log_type' => AuditLog::LOG_TYPE_PERMISSION,
            'auditable_type' => 'user',
            'auditable_id' => 2,
            'request_id' => 'req-other-001',
        ]);

        $ids = AuditLog::query()
            ->withLogType(AuditLog::LOG_TYPE_DATA)
            ->matchingRequestId('match')
            ->latestFirst()
            ->pluck('id')
            ->all();

        $this->assertSame([$matching->id], $ids);
    }

    public function test_audit_log_scope_attributes_filter_time_windows_and_actions(): void
    {
        $oldRecord = AuditLog::query()->create([
            'action' => 'audit.policy.update',
            'log_type' => AuditLog::LOG_TYPE_PERMISSION,
            'auditable_type' => 'policy',
            'auditable_id' => 1,
        ]);
        $recentRecord = AuditLog::query()->create([
            'action' => 'audit.policy.update',
            'log_type' => AuditLog::LOG_TYPE_PERMISSION,
            'auditable_type' => 'policy',
            'auditable_id' => 2,
        ]);

        $oldRecord->forceFill(['created_at' => CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC')])->saveQuietly();
        $recentRecord->forceFill(['created_at' => CarbonImmutable::parse('2026-03-10 12:00:00', 'UTC')])->saveQuietly();

        $ids = AuditLog::query()
            ->forAction('audit.policy.update')
            ->fromTimestamp(CarbonImmutable::parse('2026-03-05 00:00:00', 'UTC'))
            ->untilTimestamp(CarbonImmutable::parse('2026-03-11 00:00:00', 'UTC'))
            ->pluck('id')
            ->all();

        $this->assertSame([$recentRecord->id], $ids);

        $expiredIds = AuditLog::query()
            ->forAction('audit.policy.update')
            ->beforeTimestamp(CarbonImmutable::parse('2026-03-05 00:00:00', 'UTC'))
            ->pluck('id')
            ->all();

        $this->assertSame([$oldRecord->id], $expiredIds);
    }
}
