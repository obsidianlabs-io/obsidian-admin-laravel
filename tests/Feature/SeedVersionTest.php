<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SeedVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_seed_modules_record_applied_versions(): void
    {
        $this->seed();

        $modules = [
            'language.locales',
            'tenant.core',
            'rbac.permissions',
            'rbac.roles',
            'theme.profiles',
            'audit.policies',
            'identity.users',
        ];

        foreach ($modules as $module) {
            $version = DB::table('seed_versions')
                ->where('module', $module)
                ->where('version', 1)
                ->first();

            $this->assertNotNull($version, sprintf('Seed version record missing for module [%s]', $module));
            $this->assertNotEmpty((string) ($version->checksum ?? ''), sprintf('Seed checksum missing for module [%s]', $module));
            $this->assertNotNull($version->applied_at, sprintf('Seed applied_at missing for module [%s]', $module));
        }
    }

    public function test_reseeding_skips_applied_versions_without_duplicate_rows(): void
    {
        $this->seed();

        $before = DB::table('seed_versions')->count();
        $this->seed();
        $after = DB::table('seed_versions')->count();

        $this->assertSame($before, $after);
    }

    public function test_seed_runner_fails_when_applied_checksum_is_tampered(): void
    {
        $this->seed();

        DB::table('seed_versions')
            ->where('module', 'rbac.permissions')
            ->where('version', 1)
            ->update(['checksum' => str_repeat('0', 64)]);

        $this->expectException(RuntimeException::class);
        $this->seed(PermissionSeeder::class);
    }
}
