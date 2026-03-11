<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\System\Models\ApiAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApiAccessLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_access_log_can_record_login_request_when_enabled(): void
    {
        $this->seed();
        config()->set('audit.api_access.enabled', true);
        config()->set('audit.api_access.sample_rate', 1.0);
        config()->set('audit.api_access.excluded_paths', []);

        $response = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertDatabaseHas('api_access_logs', [
            'path' => 'api/auth/login',
            'method' => 'POST',
            'status_code' => 200,
        ]);
    }

    public function test_api_access_log_respects_excluded_paths(): void
    {
        config()->set('audit.api_access.enabled', true);
        config()->set('audit.api_access.sample_rate', 1.0);
        config()->set('audit.api_access.excluded_paths', ['api/health*']);

        $response = $this->getJson('/api/health/live');
        $response->assertOk();

        $this->assertDatabaseMissing('api_access_logs', [
            'path' => 'api/health/live',
        ]);
    }

    public function test_api_access_prune_removes_expired_logs(): void
    {
        config()->set('audit.api_access.retention_days', 30);

        $expired = $this->createAccessLog(now()->subDays(60));
        $active = $this->createAccessLog(now()->subDays(5));

        $this->artisan('api-access:prune')
            ->expectsOutputToContain('Total deleted:')
            ->assertSuccessful();

        $this->assertDatabaseMissing('api_access_logs', ['id' => $expired->id]);
        $this->assertDatabaseHas('api_access_logs', ['id' => $active->id]);
    }

    private function createAccessLog(Carbon $createdAt): ApiAccessLog
    {
        $record = ApiAccessLog::query()->create([
            'request_id' => 'req-'.$createdAt->timestamp,
            'trace_id' => 'trace-'.$createdAt->timestamp,
            'method' => 'GET',
            'path' => 'api/demo/ping',
            'route_name' => null,
            'status_code' => 200,
            'duration_ms' => 12,
            'request_size' => 0,
            'response_size' => 123,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $record->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $record;
    }
}
