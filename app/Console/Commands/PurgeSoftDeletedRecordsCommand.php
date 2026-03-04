<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Models\Team;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class PurgeSoftDeletedRecordsCommand extends Command
{
    protected $signature = 'app:purge-soft-deleted {--dry-run : Preview records that would be purged}';

    protected $description = 'Force-delete expired soft-deleted records according to security.deletion.retention_days';

    public function handle(): int
    {
        $retentionDays = max(1, (int) config('security.deletion.retention_days', 30));
        $cutoff = now()->subDays($retentionDays);
        $dryRun = (bool) $this->option('dry-run');

        /** @var array<string, class-string<Model>> $targets */
        $targets = [
            'users' => User::class,
            'roles' => Role::class,
            'permissions' => Permission::class,
            'tenants' => Tenant::class,
            'organizations' => Organization::class,
            'teams' => Team::class,
        ];

        $totalPurged = 0;
        foreach ($targets as $label => $modelClass) {
            $query = $modelClass::query()
                ->onlyTrashed()
                ->where('deleted_at', '<=', $cutoff);

            $count = (int) (clone $query)->count();
            if ($count <= 0) {
                $this->line(sprintf('%s: 0', $label));

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('%s: %d (dry-run)', $label, $count));
                $totalPurged += $count;

                continue;
            }

            $purged = (int) $query->forceDelete();
            $this->line(sprintf('%s: %d', $label, $purged));
            $totalPurged += $purged;
        }

        if ($dryRun) {
            $this->info(sprintf('[DRY-RUN] Total candidates: %d', $totalPurged));
        } else {
            $this->info(sprintf('Total purged: %d', $totalPurged));
        }

        return self::SUCCESS;
    }
}
