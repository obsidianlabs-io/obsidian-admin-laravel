<?php

declare(strict_types=1);

namespace App\Domains\System\Listeners;

use App\Domains\Shared\Contracts\AsyncAuditEvent;
use App\Domains\System\Services\AuditLogService;
use App\Jobs\WriteAuditLogJob;

final class RecordAuditEvent
{
    public function handle(AsyncAuditEvent $event): void
    {
        if (config('audit.queue.enabled', true)) {
            dispatch(new WriteAuditLogJob(
                actionName: $event->action(),
                payloadData: $event->payload(),
                tenantIdValue: $event->tenantId()
            ));
        } else {
            app(AuditLogService::class)->recordPreparedPayload(
                action: $event->action(),
                payload: $event->payload(),
                tenantId: $event->tenantId()
            );
        }
    }
}
