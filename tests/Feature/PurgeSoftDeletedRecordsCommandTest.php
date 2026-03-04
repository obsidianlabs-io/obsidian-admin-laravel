<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeSoftDeletedRecordsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_soft_deleted_removes_records_older_than_retention_days(): void
    {
        $this->seed();
        config()->set('security.deletion.retention_days', 30);

        $oldPermission = Permission::query()->create([
            'code' => 'permission.purge.old',
            'name' => 'Permission Purge Old',
            'group' => 'test',
            'description' => '',
            'status' => '2',
        ]);
        $newPermission = Permission::query()->create([
            'code' => 'permission.purge.new',
            'name' => 'Permission Purge New',
            'group' => 'test',
            'description' => '',
            'status' => '2',
        ]);

        $oldPermission->delete();
        $newPermission->delete();

        $oldPermission->forceFill([
            'deleted_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ])->save();
        $newPermission->forceFill([
            'deleted_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ])->save();

        $this->artisan('app:purge-soft-deleted')
            ->expectsOutputToContain('Total purged: 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['id' => $oldPermission->id]);
        $this->assertDatabaseHas('permissions', ['id' => $newPermission->id]);
    }

    public function test_purge_soft_deleted_dry_run_does_not_delete_records(): void
    {
        $this->seed();
        config()->set('security.deletion.retention_days', 30);

        $permission = Permission::query()->create([
            'code' => 'permission.purge.dry.run',
            'name' => 'Permission Purge Dry Run',
            'group' => 'test',
            'description' => '',
            'status' => '2',
        ]);
        $permission->delete();
        $permission->forceFill([
            'deleted_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ])->save();

        $this->artisan('app:purge-soft-deleted --dry-run')
            ->expectsOutputToContain('[DRY-RUN] Total candidates: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }
}
