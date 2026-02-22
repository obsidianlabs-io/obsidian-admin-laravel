<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\System\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WriteAuditLogJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /**
     * @var int|list<int>
     */
    public int|array $backoff;

    public int $timeout;

    /**
     * @param  array{
     *   user_id: int|null,
     *   tenant_id: int|null,
     *   action: string,
     *   auditable_type: string,
     *   auditable_id: int|null,
     *   old_values: array<string, mixed>|null,
     *   new_values: array<string, mixed>|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   request_id: string|null
     * }  $payload
     */
    public function __construct(private readonly array $payload)
    {
        $this->tries = max(1, (int) config('audit.queue.tries', 5));
        $this->backoff = $this->normalizeBackoff(config('audit.queue.backoff', [5, 30, 120]));
        $this->timeout = max(1, (int) config('audit.queue.timeout', 15));

        $connection = trim((string) config('audit.queue.connection', (string) config('queue.default', 'database')));
        $queue = trim((string) config('audit.queue.name', 'audit'));
        if ($connection !== '') {
            $this->onConnection($connection);
        }
        if ($queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(AuditLogService $auditLogService): void
    {
        $auditLogService->writeAuditLogPayload($this->payload);
    }

    /**
     * @return int|list<int>
     */
    private function normalizeBackoff(mixed $configured): int|array
    {
        if (is_numeric($configured)) {
            return max(1, (int) $configured);
        }

        if (! is_array($configured) || $configured === []) {
            return [5, 30, 120];
        }

        $normalized = array_values(array_map(
            static fn (mixed $value): int => max(1, (int) $value),
            $configured
        ));

        return $normalized;
    }
}
