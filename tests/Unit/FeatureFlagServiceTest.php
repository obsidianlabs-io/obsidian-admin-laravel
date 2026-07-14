<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\System\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeatureFlagService::class);
    }

    public function test_scope_key_with_no_tenant_and_no_roles(): void
    {
        $key = $this->service->scopeKey(null, []);

        $this->assertSame('tenant:0|roles:-', $key);
    }

    public function test_scope_key_with_tenant_and_roles(): void
    {
        $key = $this->service->scopeKey(42, ['R_ADMIN', 'R_VIEWER']);

        $this->assertSame('tenant:42|roles:R_ADMIN,R_VIEWER', $key);
    }

    public function test_scope_key_sorts_role_codes(): void
    {
        $key = $this->service->scopeKey(1, ['Z_ROLE', 'A_ROLE', 'M_ROLE']);

        $this->assertSame('tenant:1|roles:A_ROLE,M_ROLE,Z_ROLE', $key);
    }

    public function test_scope_key_deduplicates_role_codes(): void
    {
        $key = $this->service->scopeKey(1, ['R_ADMIN', 'R_ADMIN', 'R_VIEWER']);

        $this->assertSame('tenant:1|roles:R_ADMIN,R_VIEWER', $key);
    }

    public function test_scope_key_filters_empty_role_codes(): void
    {
        $key = $this->service->scopeKey(1, ['', 'R_ADMIN', '  ']);

        $this->assertSame('tenant:1|roles:R_ADMIN', $key);
    }

    public function test_scope_key_trims_role_codes(): void
    {
        $key = $this->service->scopeKey(1, ['  R_ADMIN  ']);

        $this->assertSame('tenant:1|roles:R_ADMIN', $key);
    }

    public function test_parse_scope_key_round_trip(): void
    {
        $originalKey = $this->service->scopeKey(42, ['R_ADMIN', 'R_VIEWER']);
        $parsed = $this->service->parseScopeKey($originalKey);

        $this->assertSame(42, $parsed->tenantId);
        $this->assertSame(['R_ADMIN', 'R_VIEWER'], $parsed->roleCodes);
    }

    public function test_parse_scope_key_with_no_roles(): void
    {
        $parsed = $this->service->parseScopeKey('tenant:0|roles:-');

        $this->assertSame(0, $parsed->tenantId);
        $this->assertSame([], $parsed->roleCodes);
    }

    public function test_parse_scope_key_with_invalid_format(): void
    {
        $parsed = $this->service->parseScopeKey('invalid-format');

        $this->assertSame(0, $parsed->tenantId);
        $this->assertSame([], $parsed->roleCodes);
    }

    public function test_global_scope_key_returns_global_constant(): void
    {
        $key = $this->service->globalScopeKey();

        $this->assertSame('__global__', $key);
    }

    public function test_has_feature_definition_returns_false_for_unknown(): void
    {
        $this->assertFalse($this->service->hasFeatureDefinition('nonexistent.feature'));
    }

    public function test_clear_override_cache_does_not_throw(): void
    {
        $this->service->clearOverrideCache();

        $this->assertTrue(true);
    }

    public function test_singleton_shares_same_instance(): void
    {
        $instance1 = app(FeatureFlagService::class);
        $instance2 = app(FeatureFlagService::class);

        $this->assertSame($instance1, $instance2);
    }
}
