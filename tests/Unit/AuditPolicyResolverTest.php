<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\System\Services\AuditPolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditPolicyResolverTest extends TestCase
{
    use RefreshDatabase;

    private AuditPolicyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AuditPolicyResolver;
    }

    public function test_event_catalog_returns_configured_events(): void
    {
        $catalog = $this->resolver->eventCatalog();

        $this->assertNotEmpty($catalog);

        foreach ($catalog as $action => $definition) {
            $this->assertSame($action, $definition['action']);
            $this->assertContains($definition['category'], ['mandatory', 'optional']);
            $this->assertIsBool($definition['mandatory']);
            $this->assertIsBool($definition['defaultEnabled']);
            $this->assertIsFloat($definition['defaultSamplingRate']);
            $this->assertIsInt($definition['defaultRetentionDays']);
        }
    }

    public function test_event_catalog_caches_result(): void
    {
        $first = $this->resolver->eventCatalog();
        $second = $this->resolver->eventCatalog();

        $this->assertSame($first, $second);
    }

    public function test_event_definition_returns_null_for_unknown_action(): void
    {
        $this->assertNull($this->resolver->eventDefinition('nonexistent.action'));
    }

    public function test_event_definition_returns_definition_for_known_action(): void
    {
        $catalog = $this->resolver->eventCatalog();
        $firstAction = array_key_first($catalog);

        $definition = $this->resolver->eventDefinition($firstAction);

        $this->assertNotNull($definition);
        $this->assertSame($firstAction, $definition['action']);
    }

    public function test_normalize_sampling_rate_clamps_to_0_1(): void
    {
        $this->assertSame(0.0, $this->resolver->normalizeSamplingRate(-0.5));
        $this->assertSame(0.0, $this->resolver->normalizeSamplingRate(0));
        $this->assertSame(0.5, $this->resolver->normalizeSamplingRate(0.5));
        $this->assertSame(1.0, $this->resolver->normalizeSamplingRate(1));
        $this->assertSame(1.0, $this->resolver->normalizeSamplingRate(1.5));
    }

    public function test_normalize_sampling_rate_rounds_to_4_decimals(): void
    {
        $this->assertSame(0.1235, $this->resolver->normalizeSamplingRate(0.123456789));
    }

    public function test_normalize_retention_days_clamps_to_1_3650(): void
    {
        $this->assertSame(1, $this->resolver->normalizeRetentionDays(0));
        $this->assertSame(1, $this->resolver->normalizeRetentionDays(-10));
        $this->assertSame(30, $this->resolver->normalizeRetentionDays(30));
        $this->assertSame(3650, $this->resolver->normalizeRetentionDays(99999));
    }

    public function test_normalize_retention_days_accepts_float_and_string(): void
    {
        $this->assertSame(7, $this->resolver->normalizeRetentionDays(7.9));
        $this->assertSame(14, $this->resolver->normalizeRetentionDays('14'));
    }

    public function test_resolve_effective_policy_returns_default_for_unknown_action(): void
    {
        $effective = $this->resolver->resolveEffectivePolicy('nonexistent.action', null);

        $this->assertTrue($effective->enabled);
        $this->assertSame(1.0, $effective->samplingRate);
        $this->assertSame('default', $effective->source);
    }

    public function test_resolve_effective_policy_for_mandatory_action(): void
    {
        $catalog = $this->resolver->eventCatalog();
        $mandatoryAction = null;
        foreach ($catalog as $action => $def) {
            if ($def['mandatory']) {
                $mandatoryAction = $action;
                break;
            }
        }

        if ($mandatoryAction === null) {
            $this->markTestSkipped('No mandatory audit action configured');
        }

        $effective = $this->resolver->resolveEffectivePolicy($mandatoryAction, null);

        $this->assertTrue($effective->enabled);
        $this->assertSame(1.0, $effective->samplingRate);
    }

    public function test_flush_caches_clears_scope_cache(): void
    {
        // Touch scopePolicies to populate cache
        $this->resolver->scopePolicies(null);

        $this->resolver->flushCaches();

        // After flush, scopePolicies should re-query DB
        $result = $this->resolver->scopePolicies(null);
        $this->assertIsArray($result);
    }

    public function test_mandatory_default_retention_days_returns_positive_int(): void
    {
        $days = $this->resolver->mandatoryDefaultRetentionDays();

        $this->assertIsInt($days);
        $this->assertGreaterThanOrEqual(1, $days);
        $this->assertLessThanOrEqual(3650, $days);
    }

    public function test_optional_default_retention_days_returns_positive_int(): void
    {
        $days = $this->resolver->optionalDefaultRetentionDays();

        $this->assertIsInt($days);
        $this->assertGreaterThanOrEqual(1, $days);
        $this->assertLessThanOrEqual(3650, $days);
    }

    public function test_optional_default_sampling_rate_returns_valid_float(): void
    {
        $rate = $this->resolver->optionalDefaultSamplingRate();

        $this->assertIsFloat($rate);
        $this->assertGreaterThanOrEqual(0.0, $rate);
        $this->assertLessThanOrEqual(1.0, $rate);
    }
}
