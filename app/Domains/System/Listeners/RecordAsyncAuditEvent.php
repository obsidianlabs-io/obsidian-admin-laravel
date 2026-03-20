<?php

declare(strict_types=1);

namespace App\Domains\System\Listeners;

use App\Domains\System\Contracts\AsyncAuditEvent;
use App\Domains\System\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

final class RecordAsyncAuditEvent implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries;

    /**
     * @var int|list<int>
     */
    public int|array $backoff;

    public int $timeout;

    public function __construct()
    {
        $this->tries = max(1, (int) config('audit.queue.tries', 5));
        $this->backoff = $this->normalizeBackoff(config('audit.queue.backoff', [5, 30, 120]));
        $this->timeout = max(1, (int) config('audit.queue.timeout', 15));
    }

    public function shouldQueue(AsyncAuditEvent $event): bool
    {
        unset($event);

        return (bool) config('audit.queue.enabled', true);
    }

    public function handle(AsyncAuditEvent $event): void
    {
        app(AuditLogService::class)->recordPreparedPayload(
            action: $event->action(),
            payload: $event->payload(),
            tenantId: $event->tenantId()
        );
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

        return array_values(array_map(
            static fn (mixed $value): int => max(1, (int) $value),
            $configured
        ));
    }
}
