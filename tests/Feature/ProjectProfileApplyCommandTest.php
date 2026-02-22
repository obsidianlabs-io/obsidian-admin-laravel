<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectProfileApplyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_profile_apply_updates_global_audit_overrides(): void
    {
        $this->artisan('project:profile:apply base')
            ->expectsOutputToContain('Applying profile "base"')
            ->expectsOutputToContain('Audit overrides applied')
            ->assertExitCode(0);

        $this->assertDatabaseHas('audit_policies', [
            'tenant_scope_id' => 0,
            'action' => 'auth.login',
            'enabled' => 1,
            'retention_days' => 60,
        ]);
    }

    public function test_project_profile_apply_returns_error_for_unknown_profile(): void
    {
        $this->artisan('project:profile:apply unknown-profile')
            ->expectsOutputToContain('Unknown project profile')
            ->assertExitCode(1);
    }

    public function test_project_profile_apply_can_write_env_file(): void
    {
        $envPath = base_path('storage/framework/testing/profile-env.env');
        File::ensureDirectoryExists(dirname($envPath));
        File::put($envPath, "APP_NAME=\"Obsidian\"\nAUTH_SUPER_ADMIN_REQUIRE_2FA=false\n");

        try {
            $this->artisan(sprintf(
                'project:profile:apply strict-enterprise --write-env --env-file=%s --no-audit',
                $envPath
            ))
                ->expectsOutputToContain('Env overrides written to')
                ->assertExitCode(0);

            $updated = File::get($envPath);
            $this->assertStringContainsString('AUTH_SUPER_ADMIN_REQUIRE_2FA=true', $updated);
            $this->assertStringContainsString('AUTH_REQUIRE_EMAIL_VERIFICATION=true', $updated);
            $this->assertStringContainsString('MENU_FEATURE_AUDIT=true', $updated);
        } finally {
            File::delete($envPath);
        }
    }
}
