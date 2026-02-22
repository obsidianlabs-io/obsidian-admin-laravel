<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\System\Services\AuditPolicyService;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'audit:prune {--dry-run : Preview the number of rows that would be deleted}';

    /**
     * @var string
     */
    protected $description = 'Prune audit logs based on audit retention policies';

    public function __construct(private readonly AuditPolicyService $auditPolicyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $this->auditPolicyService->pruneExpiredLogs($dryRun);

        $mode = $dryRun ? 'DRY-RUN' : 'DELETE';
        $this->info(sprintf('[%s] Audit prune completed.', $mode));
        $this->line(sprintf('Known actions: %d', (int) $result['actionCount']));
        $this->line(sprintf('Unknown-action deletions: %d', (int) $result['unknownDeleted']));
        $this->line(sprintf('Total deleted: %d', (int) $result['totalDeleted']));

        return self::SUCCESS;
    }
}
