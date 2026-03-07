<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\System\Services\ApiAccessLogService;
use Illuminate\Console\Command;

class PruneApiAccessLogsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'api-access:prune {--dry-run : Preview the number of rows that would be deleted}';

    /**
     * @var string
     */
    protected $description = 'Prune API access logs based on retention policy';

    public function __construct(private readonly ApiAccessLogService $apiAccessLogService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $this->apiAccessLogService->pruneExpiredLogs($dryRun);

        $mode = $dryRun ? 'DRY-RUN' : 'DELETE';
        $this->info(sprintf('[%s] API access log prune completed.', $mode));
        $this->line(sprintf('Retention days: %d', $result->retentionDays));
        $this->line(sprintf('Total deleted: %d', $result->totalDeleted));

        return self::SUCCESS;
    }
}
